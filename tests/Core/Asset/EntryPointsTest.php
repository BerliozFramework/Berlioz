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

namespace Berlioz\Core\Tests\Asset;

use Berlioz\Core\Asset\EntryPoints;
use Berlioz\Core\Exception\AssetException;
use PHPUnit\Framework\TestCase;

class EntryPointsTest extends TestCase
{
    public function testTarget()
    {
        $entryPoints = new EntryPoints(__DIR__ . '/files/entrypoints.target.json', 'entrypoints');
        $this->assertInstanceOf(EntryPoints::class, $entryPoints);
        $this->assertEquals(
            [
                '/assets/js/test.12345678.js',
                '/assets/js/test2.12345678.js',
                '/assets/js/vendor.12345678.js'
            ],
            $entryPoints->get('test', 'js')
        );
    }

    public function testBadTarget()
    {
        $this->expectException(AssetException::class);
        $entryPoints = new EntryPoints(__DIR__ . '/files/entrypoints.json', 'entrypoints');
        $entryPoints->get('test');
    }

    public function testGet()
    {
        $entryPoints = new EntryPoints(__DIR__ . '/files/entrypoints.json');

        $this->assertEquals(
            [
                '/assets/js/test.12345678.js',
                '/assets/js/test2.12345678.js',
                '/assets/js/vendor.12345678.js'
            ],
            $entryPoints->get('test', 'js')
        );
        $this->assertEquals(
            [
                'js' => [
                    '/assets/js/test.12345678.js',
                    '/assets/js/test2.12345678.js',
                    '/assets/js/vendor.12345678.js'
                ],
                'css' => ['/assets/css/test.12345678.css']
            ],
            $entryPoints->get('test')
        );
        $this->assertEquals([], $entryPoints->get('foo', 'js'));
        $this->assertEquals([], $entryPoints->get('test', 'bar'));
    }

    public function testGet_multipleEntry()
    {
        $entryPoints = new EntryPoints(__DIR__ . '/files/entrypoints.json');

        $this->assertEquals(
            [
                '/assets/css/test.12345678.css',
                '/assets/css/test2.12345678.css'
            ],
            $entryPoints->get(['test', 'test2'], 'css')
        );
        $this->assertEquals(
            [
                'js' => [
                    '/assets/js/test.12345678.js',
                    '/assets/js/test2.12345678.js',
                    '/assets/js/vendor.12345678.js'
                ],
                'css' => [
                    '/assets/css/test.12345678.css',
                    '/assets/css/test2.12345678.css'
                ]
            ],
            $entryPoints->get(['test', 'test2'])
        );
        $this->assertEquals([], $entryPoints->get(['foo', 'bar'], 'js'));
    }

    public function testGetOnBadFilename()
    {
        $entryPoints = new EntryPoints('fake.json');

        $this->expectException(AssetException::class);
        $entryPoints->get('test');
    }
}
