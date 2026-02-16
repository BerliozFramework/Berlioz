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

namespace Berlioz\Core\Tests\Event;

use Berlioz\Core\Core;
use Berlioz\Core\Debug\DebugHandler;
use Berlioz\Core\Event\EventDispatcher;
use Berlioz\Core\Tests\Directories\FakeDefaultDirectories;
use Berlioz\Core\Tests\RestoresErrorHandler;
use Berlioz\EventManager\Provider\ListenerProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;

class EventDispatcherTest extends TestCase
{
    use RestoresErrorHandler;

    public function test__construct()
    {
        $dispatcher = new EventDispatcher(new DebugHandler(), $provider = new ListenerProvider());

        $reflection = new ReflectionProperty(\Berlioz\EventManager\EventDispatcher::class, 'defaultProvider');
        $reflection->setAccessible(true);

        $this->assertSame($provider, $reflection->getValue($dispatcher));
    }

    public function testDispatch()
    {
        $core = new Core(new FakeDefaultDirectories());
        $dispatcher = new EventDispatcher($debug = new DebugHandler());
        $debug->handle($core);
        $debug->setEnabled(true);
        $dispatcher->dispatch(new stdClass());

        $this->assertCount(1, $debug->getSnapshot()->getEvents());
        $this->assertEquals(
            stdClass::class,
            iterator_to_array($debug->getSnapshot()->getEvents()->getEvents(), false)[0]->getName()
        );
    }
}
