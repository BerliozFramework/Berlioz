<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2021 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\Core\Tests\App;

use Berlioz\Core\App\AbstractApp;
use Berlioz\Core\Asset\Assets;
use Berlioz\Core\Core;
use Berlioz\Core\Event\TerminateEvent;
use Berlioz\Core\Tests\Directories\FakeDefaultDirectories;
use Berlioz\Core\Tests\RestoresErrorHandler;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AbstractAppTest extends TestCase
{
    use RestoresErrorHandler;

    private function getApp()
    {
        $core = new Core(new FakeDefaultDirectories(), false);
        $app = new class($core) extends AbstractApp {
            protected function boot(): void
            {
            }
        };

        return [$app, $core];
    }

    public function testGetCore()
    {
        /** @var AbstractApp $app */
        [$app, $core] = $this->getApp();

        $this->assertSame($app->getCore(), $core);
    }

    public function testGet()
    {
        /** @var AbstractApp $app */
        [$app, $core] = $this->getApp();

        $this->assertSame($app->get(Assets::class), $core->getContainer()->get(Assets::class));
    }

    public function testCall()
    {
        /** @var AbstractApp $app */
        [$app, $core] = $this->getApp();
        $function = fn(string $value) => 'foo' . $value;

        $this->assertEquals('foobar', $app->call($function, ['value' => 'bar']));
        $this->assertEquals(
            $app->call($function, ['value' => 'bar']),
            $core->getContainer()->call($function, ['value' => 'bar'])
        );
    }

    public function testGetAssets()
    {
        /** @var AbstractApp $app */
        [$app, $core] = $this->getApp();

        $this->assertSame($app->getAssets(), $core->getContainer()->get(Assets::class));
    }

    public function testGetConfig()
    {
        /** @var AbstractApp $app */
        [$app, $core] = $this->getApp();

        $this->assertSame($app->getConfig(), $core->getConfig());
    }

    public function testGetConfigKey()
    {
        /** @var AbstractApp $app */
        [$app, $core] = $this->getApp();

        $this->assertEquals($app->getConfigKey('berlioz.http'), $core->getConfig()->get('berlioz.http'));
    }

    public function testGetConfigKey_withDefaultValue()
    {
        /** @var AbstractApp $app */
        [$app, $core] = $this->getApp();

        $this->assertEquals('foo', $app->getConfigKey('berlioz.fake', 'foo'));
        $this->assertEquals($app->getConfigKey('berlioz.fake', 'foo'), $core->getConfig()->get('berlioz.fake', 'foo'));
    }

    public function testGetDebug()
    {
        /** @var AbstractApp $app */
        [$app, $core] = $this->getApp();

        $this->assertSame($app->getDebug(), $core->getDebug());
    }

    public function testTerminate()
    {
        /** @var AbstractApp $app */
        /** @var Core $core */
        [$app, $core] = $this->getApp();
        $events = [];
        $core->getEventDispatcher()->addEventListener(
            TerminateEvent::class,
            function (TerminateEvent $event) use (&$events): void {
                $events[] = $event;
            }
        );

        $app->terminate();
        $app->terminate();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(TerminateEvent::class, $events[0]);
        $this->assertSame($app, $events[0]->getApp());
    }

    public function testTerminate_debugActivity()
    {
        /** @var AbstractApp $app */
        /** @var Core $core */
        [$app, $core] = $this->getApp();
        $core->getDebug()->setEnabled(true);

        $app->terminate();

        $activities = $core->getDebug()->getSnapshot()->getTimeline()->getActivities(AbstractApp::TERMINATE_GROUP);
        $this->assertCount(1, $activities);
        $this->assertNotNull(reset($activities)->duration());
    }

    public function testTerminate_listenerException()
    {
        /** @var AbstractApp $app */
        /** @var Core $core */
        [$app, $core] = $this->getApp();
        $core->getDebug()->setEnabled(true);
        $core->getEventDispatcher()->addEventListener(
            TerminateEvent::class,
            fn() => throw new RuntimeException('Listener failure')
        );
        $warnings = [];
        set_error_handler(function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = [$severity, $message];

            return true;
        });

        try {
            $app->terminate();
        } finally {
            restore_error_handler();
        }

        $this->assertSame(
            [[E_USER_WARNING, 'Error during application terminate: Listener failure']],
            $warnings,
        );
        $this->assertCount(1, $core->getDebug()->getSnapshot()->getExceptions());
        $this->assertStringContainsString('Listener failure', $core->getDebug()->getSnapshot()->getExceptions()[0]);
    }
}
