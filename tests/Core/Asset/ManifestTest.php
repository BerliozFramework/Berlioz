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

use Berlioz\Core\Asset\Manifest;
use Berlioz\Core\Exception\AssetException;
use PHPUnit\Framework\TestCase;

class ManifestTest extends TestCase
{
    private function getManifest(): Manifest
    {
        return new Manifest(__DIR__ . '/files/manifest.json');
    }

    public function testHasAsset()
    {
        $manifest = $this->getManifest();

        $this->assertTrue($manifest->has('/test.js'));
        $this->assertTrue($manifest->has('/test2.js'));
        $this->assertTrue($manifest->has('/test3.js'));
        $this->assertFalse($manifest->has('\\test3.js'));
    }

    public function testGet()
    {
        $manifest = $this->getManifest();

        $this->assertEquals('/assets/test.1234567890.js', $manifest->get('/test.js'));
        $this->assertEquals('/assets/test2.1234567890.js', $manifest->get('/test2.js'));
        $this->assertEquals('/assets/test3.1234567890.js', $manifest->get('/test3.js'));
    }

    public function testGetNotExists()
    {
        $this->expectException(AssetException::class);

        $manifest = $this->getManifest();
        $manifest->get('/test-notexists.js');
    }

    public function testGetOnBadFilename()
    {
        $manifest = new Manifest('fake.json');

        $this->expectException(AssetException::class);
        $manifest->get('/test.js');
    }
}
