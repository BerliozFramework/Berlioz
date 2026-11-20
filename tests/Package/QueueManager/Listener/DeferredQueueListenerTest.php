<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Package\QueueManager\Tests\Listener;

use Berlioz\Config\Adapter\ArrayAdapter;
use Berlioz\Core\App\AbstractApp;
use Berlioz\Core\Core;
use Berlioz\Core\Event\TerminateEvent;
use Berlioz\Core\Tests\RestoresErrorHandler;
use Berlioz\Package\QueueManager\BerliozPackage;
use Berlioz\Package\QueueManager\TestProject\TestEnvDirectories;
use Berlioz\Package\QueueManager\Tests\Fake\FakeJobHandler;
use Berlioz\QueueManager\Job\JobDescriptor;
use Berlioz\QueueManager\Queue\MemoryQueue;
use Berlioz\QueueManager\QueueManager;
use PHPUnit\Framework\TestCase;

class DeferredQueueListenerTest extends TestCase
{
    use RestoresErrorHandler;

    protected function setUp(): void
    {
        FakeJobHandler::$handled = [];
    }

    private function getCore(array $deferred): Core
    {
        $core = new Core(new TestEnvDirectories(), cache: false);
        $core->getConfig()->addConfig(BerliozPackage::config());
        $core->getConfig()->addConfig(new ArrayAdapter(
            [
                'berlioz' => [
                    'queues' => [
                        'queues' => [
                            ['type' => MemoryQueue::class, 'name' => 'deferred', 'max_attempts' => 1],
                            ['type' => MemoryQueue::class, 'name' => 'default'],
                        ],
                        'handlers' => ['fake' => FakeJobHandler::class],
                        'deferred' => $deferred,
                    ],
                ],
            ],
            priority: PHP_INT_MAX,
        ));
        BerliozPackage::register($core->getContainer());
        BerliozPackage::boot($core);

        return $core;
    }

    private function terminate(Core $core): void
    {
        $app = new class($core) extends AbstractApp {
            protected function boot(): void
            {
            }
        };

        $core->getEventDispatcher()->dispatch(new TerminateEvent($app));
    }

    public function testOnTerminate(): void
    {
        $core = $this->getCore(['queues' => ['deferred']]);
        $core->getDebug()->setEnabled(true);
        /** @var QueueManager $queueManager */
        $queueManager = $core->getContainer()->get(QueueManager::class);
        $queueManager->push(new JobDescriptor('fake', ['id' => 1]), queue: 'deferred');
        $queueManager->push(new JobDescriptor('fake', ['id' => 2]), queue: 'deferred');
        $queueManager->push(new JobDescriptor('fake', ['fail' => true]), queue: 'deferred');
        $queueManager->push(new JobDescriptor('fake', ['id' => 3]), queue: 'default');

        $this->terminate($core);

        $this->assertEqualsCanonicalizing([1, 2], FakeJobHandler::$handled);
        $this->assertSame(0, $queueManager->filter('deferred')->size());
        $this->assertSame(1, $queueManager->filter('default')->size());

        $activities = $core->getDebug()->getSnapshot()->getTimeline()->getActivities(AbstractApp::TERMINATE_GROUP);
        $this->assertSame(
            ['Deferred queues', 'Deferred job: fake', 'Deferred job: fake', 'Deferred job: fake'],
            array_values(array_map(fn($activity) => $activity->getName(), $activities)),
        );
        $this->assertSame(3, reset($activities)->getResult());
        $this->assertSame(
            ['succeeded', 'succeeded', 'failed'],
            array_values(array_map(fn($activity) => $activity->getResult(), array_slice($activities, 1))),
        );
    }

    public function testOnTerminate_purgesMemoryQueues(): void
    {
        $core = $this->getCore(['queues' => ['deferred']]);
        /** @var QueueManager $queueManager */
        $queueManager = $core->getContainer()->get(QueueManager::class);
        $queueManager->push(new JobDescriptor('fake', ['id' => 1]), queue: 'deferred', delay: 3600);
        /** @var MemoryQueue $deferredQueue */
        $deferredQueue = iterator_to_array($queueManager->filter('deferred')->getQueues())[0];
        $this->assertSame(1, $deferredQueue->delayed());

        $this->terminate($core);

        // Delayed job not consumable, but removed: memory queues do not survive the terminate
        $this->assertSame([], FakeJobHandler::$handled);
        $this->assertSame(0, $deferredQueue->delayed());
    }

    public function testOnTerminate_notConfigured(): void
    {
        $core = $this->getCore(['queues' => []]);
        /** @var QueueManager $queueManager */
        $queueManager = $core->getContainer()->get(QueueManager::class);
        $queueManager->push(new JobDescriptor('fake', ['id' => 1]), queue: 'deferred');

        $this->terminate($core);

        $this->assertSame([], FakeJobHandler::$handled);
        $this->assertSame(1, $queueManager->filter('deferred')->size());
    }

    public function testOnTerminate_unknownQueue(): void
    {
        $core = $this->getCore(['queues' => ['unknown']]);
        $core->getDebug()->setEnabled(true);
        $warnings = [];
        set_error_handler(function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = [$severity, $message];

            return true;
        });

        try {
            $this->terminate($core);
        } finally {
            restore_error_handler();
        }

        $this->assertCount(1, $warnings);
        $this->assertSame(E_USER_WARNING, $warnings[0][0]);
        $this->assertStringStartsWith('Unable to consume deferred queues:', $warnings[0][1]);
        $this->assertCount(1, $core->getDebug()->getSnapshot()->getExceptions());
    }

    public function testOnTerminate_timeLimit(): void
    {
        $core = $this->getCore(['queues' => ['deferred'], 'time_limit' => 0]);
        /** @var QueueManager $queueManager */
        $queueManager = $core->getContainer()->get(QueueManager::class);
        $queueManager->push(new JobDescriptor('fake', ['id' => 1]), queue: 'deferred');

        $this->terminate($core);

        // Time limit reached before the first job; memory queue purged anyway
        $this->assertSame([], FakeJobHandler::$handled);
        $this->assertSame(0, $queueManager->filter('deferred')->size());
    }
}
