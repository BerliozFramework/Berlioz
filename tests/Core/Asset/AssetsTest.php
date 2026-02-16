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

use Berlioz\Core\Asset\Assets;
use Berlioz\Core\Asset\EntryPoints;
use Berlioz\Core\Asset\Manifest;
use PHPUnit\Framework\TestCase;

class AssetsTest extends TestCase
{
    public function testEmpty()
    {
        $assets = new Assets();

        $this->assertNull($assets->getManifest());
        $this->assertNull($assets->getEntryPoints());
    }

    public function testGetEntryPoints()
    {
        $assets = new Assets(
            manifestFile: null,
            entryPointsFile: __DIR__ . '/files/entrypoints.json',
            entryPointsKey: null,
        );

        $this->assertInstanceOf(EntryPoints::class, $entryPoints = $assets->getEntryPoints());
        $this->assertSame($entryPoints, $assets->getEntryPoints());
    }

    public function testGetManifest()
    {
        $assets = new Assets(manifestFile: __DIR__ . '/files/manifest.json');

        $this->assertInstanceOf(Manifest::class, $manifest = $assets->getManifest());
        $this->assertSame($manifest, $assets->getManifest());
    }
}
