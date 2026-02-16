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

namespace Berlioz\Core\Tests\Cache;

use Berlioz\Core\Cache\NullCacheDriver;
use PHPUnit\Framework\TestCase;

class NullCacheDriverTest extends TestCase
{
    public function testGet()
    {
        $nullDriver = new NullCacheDriver();

        $this->assertEquals('foo', $nullDriver->get('bar', 'foo'));
    }

    public function testSet()
    {
        $nullDriver = new NullCacheDriver();

        $this->assertTrue($nullDriver->set('foo', 'bar'));
    }

    public function testDelete()
    {
        $nullDriver = new NullCacheDriver();

        $this->assertTrue($nullDriver->delete('foo'));
    }

    public function testHas()
    {
        $nullDriver = new NullCacheDriver();

        $this->assertFalse($nullDriver->has('foo'));
    }

    public function testGetMultiple()
    {
        $nullDriver = new NullCacheDriver();

        $this->assertEquals(['bar' => 'qux', 'foo' => 'qux'], $nullDriver->getMultiple(['bar', 'foo'], 'qux'));
    }

    public function testSetMultiple()
    {
        $nullDriver = new NullCacheDriver();

        $this->assertTrue($nullDriver->setMultiple(['bar' => 'qux', 'foo' => 'qux']));
    }

    public function testDeleteMultiple()
    {
        $nullDriver = new NullCacheDriver();

        $this->assertTrue($nullDriver->deleteMultiple(['bar', 'foo']));
    }

    public function testClear()
    {
        $nullDriver = new NullCacheDriver();

        $this->assertTrue($nullDriver->clear());
    }
}
