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

namespace Berlioz\Core\Tests\Debug;

use Berlioz\Config\Adapter\ArrayAdapter;
use Berlioz\Core\Core;
use Berlioz\Core\Debug\DebugHandler;
use Berlioz\Core\Debug\Snapshot\Event;
use Berlioz\Core\Debug\Snapshot\TimelineActivity;
use Berlioz\Core\Exception\BerliozException;
use Berlioz\Core\Tests\Directories\FakeDefaultDirectories;
use Berlioz\Core\Tests\RestoresErrorHandler;
use DateTime;
use PHPUnit\Framework\TestCase;

class DebugHandlerTest extends TestCase
{
    use RestoresErrorHandler;

    public function testReset()
    {
        $handler = new DebugHandler();
        $handler->handle(new Core(new FakeDefaultDirectories()));

        $handler->setEnabled(true);
        $handler->addSection(new FakeSection());

        $this->assertTrue($handler->isEnabled());
        $this->assertCount(1, $handler->getSnapshot()->getSections());

        $handler->reset();

        $this->assertFalse($handler->isEnabled());
        $this->assertCount(0, $handler->getSnapshot()->getSections());
    }

    public function testAddSection()
    {
        $handler = new DebugHandler();
        $handler->handle(new Core(new FakeDefaultDirectories()));
        $handler->setEnabled(true);

        $this->assertCount(0, $handler->getSnapshot()->getSections());

        $handler->addSection($section = new FakeSection());
        $sections = $handler->getSnapshot()->getSections();

        $this->assertCount(1, $handler->getSnapshot()->getSections());
        $this->assertSame($section, reset($sections));
    }

    public function testAddSection_debugDisabled()
    {
        $handler = new DebugHandler();
        $handler->handle(new Core(new FakeDefaultDirectories()));
        $handler->setEnabled(false);

        $this->assertCount(0, $handler->getSnapshot()->getSections());
        $handler->addSection(new FakeSection());
        $this->assertCount(0, $handler->getSnapshot()->getSections());
    }

    public function testIsEnabled()
    {
        $handler = new DebugHandler();
        $handler->handle(new Core(new FakeDefaultDirectories()));
        $handler->setEnabled(true);

        $this->assertTrue($handler->isEnabled());

        $handler->setEnabled(false);

        $this->assertFalse($handler->isEnabled());
    }

    public function testAddActivity()
    {
        $handler = new DebugHandler();
        $handler->handle(new Core(new FakeDefaultDirectories()));
        $handler->setEnabled(true);

        $this->assertCount(0, $handler->getSnapshot()->getTimeline());
        $handler->addActivity(new TimelineActivity('foo'));
        $this->assertCount(1, $handler->getSnapshot()->getTimeline());
    }

    public function testAddActivity_debugDisabled()
    {
        $handler = new DebugHandler();
        $handler->handle(new Core(new FakeDefaultDirectories()));
        $handler->setEnabled(false);

        $this->assertCount(0, $handler->getSnapshot()->getTimeline());
        $handler->addActivity(new TimelineActivity('foo'));
        $this->assertCount(0, $handler->getSnapshot()->getTimeline());
    }

    public function testNewActivity()
    {
        $handler = new DebugHandler();
        $handler->handle(new Core(new FakeDefaultDirectories()));
        $handler->setEnabled(true);

        $this->assertCount(0, $handler->getSnapshot()->getTimeline());

        $activity = $handler->newActivity($name = 'foo');

        $this->assertCount(1, $handler->getSnapshot()->getTimeline());
        $this->assertSame($activity, $handler->getSnapshot()->getTimeline()->getActivities()[0]);
        $this->assertEquals($name, $handler->getSnapshot()->getTimeline()->getActivities()[0]->getName());
    }

    public function testNewActivity_debugDisabled()
    {
        $handler = new DebugHandler();
        $handler->handle(new Core(new FakeDefaultDirectories()));
        $handler->setEnabled(false);

        $this->assertCount(0, $handler->getSnapshot()->getTimeline());
        $handler->newActivity('foo');
        $this->assertCount(0, $handler->getSnapshot()->getTimeline());
    }

    public function testNewEventActivity()
    {
        $handler = new DebugHandler();
        $handler->handle(new Core(new FakeDefaultDirectories()));
        $handler->setEnabled(true);

        $this->assertCount(0, $handler->getSnapshot()->getTimeline());

        $activity = $handler->newEventActivity($event = new Event('anEvent', new DateTime()));

        $this->assertCount(1, $handler->getSnapshot()->getTimeline());
        $this->assertSame($activity, $handler->getSnapshot()->getTimeline()->getActivities()[0]);
        $this->assertEquals($event->getName(), $handler->getSnapshot()->getTimeline()->getActivities()[0]->getName());
    }

    public function testNewEventActivity_debugDisabled()
    {
        $handler = new DebugHandler();
        $handler->handle(new Core(new FakeDefaultDirectories()));
        $handler->setEnabled(false);

        $this->assertCount(0, $handler->getSnapshot()->getTimeline());
        $handler->newEventActivity(new Event('anEvent', new DateTime()));
        $this->assertCount(0, $handler->getSnapshot()->getTimeline());
    }

    public function testAddEvent()
    {
        $handler = new DebugHandler();
        $handler->handle(new Core(new FakeDefaultDirectories()));
        $handler->setEnabled(true);

        $this->assertCount(0, $handler->getSnapshot()->getEvents());
        $handler->addEvent(new Event('anEvent', new DateTime()));
        $this->assertCount(1, $handler->getSnapshot()->getEvents());
    }

    public function testAddEvent_debugDisabled()
    {
        $handler = new DebugHandler();
        $handler->handle(new Core(new FakeDefaultDirectories()));
        $handler->setEnabled(false);

        $this->assertCount(0, $handler->getSnapshot()->getEvents());
        $handler->addEvent(new Event('anEvent', new DateTime()));
        $this->assertCount(0, $handler->getSnapshot()->getEvents());
    }

    public function testNewEvent()
    {
        $handler = new DebugHandler();
        $handler->handle(new Core(new FakeDefaultDirectories()));
        $handler->setEnabled(true);

        $this->assertCount(0, $handler->getSnapshot()->getEvents());

        $handler->newEvent('anEvent', new DateTime());
        $handler->newEvent('secondEvent');

        $this->assertCount(2, $handler->getSnapshot()->getEvents());
    }

    public function testNewEvent_debugDisabled()
    {
        $handler = new DebugHandler();
        $handler->handle(new Core(new FakeDefaultDirectories()));
        $handler->setEnabled(false);

        $this->assertCount(0, $handler->getSnapshot()->getEvents());
        $handler->newEvent('anEvent', new DateTime());
        $this->assertCount(0, $handler->getSnapshot()->getEvents());
    }

    public function testAddException()
    {
        $handler = new DebugHandler();
        $handler->handle(new Core(new FakeDefaultDirectories()));
        $handler->setEnabled(true);

        $this->assertCount(0, $handler->getSnapshot()->getExceptions());

        $handler->addException($exception = new BerliozException('Foo error', 123));

        $this->assertCount(1, $handler->getSnapshot()->getExceptions());
        $this->assertEquals((string)$exception, $handler->getSnapshot()->getExceptions()[0]);
    }

    public function testAddException_debugDisabled()
    {
        $handler = new DebugHandler();
        $handler->handle(new Core(new FakeDefaultDirectories()));
        $handler->setEnabled(false);

        $this->assertCount(0, $handler->getSnapshot()->getExceptions());
        $handler->addException(new BerliozException('Foo error', 123));
        $this->assertCount(0, $handler->getSnapshot()->getExceptions());
    }

    public function testIsEnabledInConfig()
    {
        $debug = new DebugHandler();
        $debug->handle(new Core(new FakeDefaultDirectories()));

        $config = new ArrayAdapter(['berlioz' => ['debug' => ['enable' => true]]]);
        $this->assertTrue($debug->isEnabledInConfig($config));

        $config = new ArrayAdapter(['berlioz' => ['debug' => ['enable' => false]]]);
        $this->assertFalse($debug->isEnabledInConfig($config));

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $config = new ArrayAdapter(['berlioz' => ['debug' => ['enable' => false, 'ip' => ['127.0.0.1']]]]);
        $this->assertFalse($debug->isEnabledInConfig($config));

        $config = new ArrayAdapter(['berlioz' => ['debug' => ['enable' => true, 'ip' => ['127.0.0.1']]]]);
        $this->assertTrue($debug->isEnabledInConfig($config));

        $_SERVER['REMOTE_ADDR'] = '127.0.0.2';

        $this->assertFalse($debug->isEnabledInConfig($config));

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1,127.0.0.2';

        $this->assertTrue($debug->isEnabledInConfig($config));

        $_SERVER['REMOTE_ADDR'] = '127.0.0.3, 127.0.0.4';

        $this->assertFalse($debug->isEnabledInConfig($config));

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $config = new ArrayAdapter([
            'berlioz' => [
                'debug' => [
                    'enable' => true,
                    'ip' => [gethostbyaddr('127.0.0.1')]
                ]
            ]
        ]);
        $this->assertTrue($debug->isEnabledInConfig($config));
    }
}
