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

namespace Berlioz\ServiceContainer\Tests\Service;

use Berlioz\ServiceContainer\Exception\ContainerException;
use Berlioz\ServiceContainer\Service\CacheStrategy;
use Berlioz\ServiceContainer\Service\Service;
use Berlioz\ServiceContainer\Tests\Asset\Service4;
use PHPUnit\Framework\TestCase;
use stdClass;

class CacheStrategyTest extends TestCase
{
    public function test()
    {
        $service = new Service(Service4::class);
        $service->setNullable(true);
        $cacheStrategy = new CacheStrategy(new MemoryCacheDriver());

        $this->assertNull($cacheStrategy->get($service));
        $this->assertFalse($cacheStrategy->has($service));

        $cacheStrategy->set($service, null);

        $this->assertNull($cacheStrategy->get($service));
        $this->assertTrue($cacheStrategy->has($service));

        $cacheStrategy->set($service, $obj = new Service4());

        $this->assertSame($obj, $cacheStrategy->get($service));
        $this->assertTrue($cacheStrategy->has($service));
    }

    public function testCacheIntegrity()
    {
        $this->expectException(ContainerException::class);

        $service = new Service(Service4::class);
        $cacheStrategy = new CacheStrategy(new MemoryCacheDriver());

        $cacheStrategy->set($service, new stdClass());
        $cacheStrategy->get($service);
    }
}
