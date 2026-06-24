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

use ArrayObject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\CacheInterface;
use stdClass;

abstract class AbstractCacheDriverTestCase extends TestCase
{
    abstract protected function getCacheDriver(): CacheInterface;

    public static function providerSet()
    {
        return [
            ['key', 'value', true],
            ['key-key', new stdClass(), true],
            ['', 'value', false]
        ];
    }

    public static function provider()
    {
        return [
            ['key', 'value', null],
            ['key2', new stdClass(), null],
            ['key3', 1, 2],
            ['key4', ['array'], null],
            ['key5', 'value', null],
            ['key6', 'value', null],
            ['key7', 'value', null]
        ];
    }

    public static function providerMultiple()
    {
        return [
            [
                [
                    'key' => 'value',
                    'key2' => new stdClass(),
                    'key3' => 1
                ],
                null
            ],
            [
                [
                    'key4' => ['array'],
                    'key5' => 'value',
                    'key6' => 'value'
                ],
                null
            ],
            [
                ['key7' => 'value'],
                null
            ],
            [
                new ArrayObject(
                    [
                        'key4' => ['array'],
                        'key5' => 'value',
                        'key6' => 'value'
                    ]
                ),
                null
            ]
        ];
    }

    #[DataProvider('providerSet')]
    public function testSet($key, $value, $valid)
    {
        if (!$valid) {
            $this->expectException(CacheException::class);
        }

        $this->getCacheDriver()->set($key, $value, 0);

        $this->assertTrue(true);
    }

    #[DataProvider('provider')]
    public function testGet($key, $value, $ttl)
    {
        $this->getCacheDriver()->set($key, $value, $ttl);
        $this->assertEquals($value, $this->getCacheDriver()->get($key));

        if (!is_null($ttl)) {
            // Sleep slightly longer than the TTL to avoid an expiry race at the exact boundary.
            usleep($ttl * 1_000_000 + 500_000);
            $this->assertNull($this->getCacheDriver()->get($key));
            $this->assertEquals('test', $this->getCacheDriver()->get($key, 'test'));
        }
    }

    #[DataProvider('provider')]
    public function testHas($key, $value, $ttl)
    {
        if (is_null($ttl)) {
            $this->assertTrue($this->getCacheDriver()->has($key));
        } else {
            $this->assertFalse($this->getCacheDriver()->has($key));
        }
    }

    #[DataProvider('provider')]
    public function testDelete($key, $value, $ttl)
    {
        $this->assertTrue($this->getCacheDriver()->delete($key));
        $this->assertFalse($this->getCacheDriver()->has($key));
    }

    #[DataProvider('providerMultiple')]
    public function testSetMultiple($dataSet, $ttl)
    {
        $this->assertTrue($this->getCacheDriver()->setMultiple($dataSet, $ttl));
    }

    #[DataProvider('providerMultiple')]
    public function testGetMultiple($dataSet)
    {
        if (is_array($dataSet)) {
            $keys = array_keys($dataSet);
            $result = $dataSet;
        }
        if ($dataSet instanceof ArrayObject) {
            $keys = array_keys($dataSet->getArrayCopy());
            $result = $dataSet->getArrayCopy();
        }

        $values = $this->getCacheDriver()->getMultiple($keys);
        $this->assertEquals($result, $values);
    }

    #[DataProvider('providerMultiple')]
    public function testDeleteMultiple($dataSet)
    {
        if (is_array($dataSet)) {
            $keys = array_keys($dataSet);
        }
        if ($dataSet instanceof ArrayObject) {
            $keys = array_keys($dataSet->getArrayCopy());
        }

        $this->assertTrue($this->getCacheDriver()->deleteMultiple($keys));
        $values = $this->getCacheDriver()->getMultiple($keys);
        $this->assertEquals([], array_filter($values));
    }
}
