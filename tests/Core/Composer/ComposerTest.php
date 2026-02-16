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

namespace Berlioz\Core\Tests\Composer;

use Berlioz\Core\Composer\Composer;
use Berlioz\Core\Composer\Package;
use PHPUnit\Framework\TestCase;

class ComposerTest extends TestCase
{
    public function testGetName()
    {
        $composer = new Composer($name = 'berlioz');

        $this->assertEquals($name, $composer->getName());
    }

    public function testGetVersion()
    {
        $composer = new Composer('berlioz', $version = '1.0');

        $this->assertEquals($version, $composer->getVersion());
    }

    public function testGetVersion_null()
    {
        $composer = new Composer('berlioz');

        $this->assertNull($composer->getVersion());
    }

    public function testAddPackage()
    {
        $packages = [];
        $composer = new Composer('berlioz');
        $composer->addPackage(
            $packages[] = new Package('foo'),
            $packages[] = new Package('bar')
        );

        $this->assertSame($packages, iterator_to_array($composer->getPackages(), false));
    }

    public function testGetPackages()
    {
        $composer = new Composer('berlioz');
        $composer->addPackage(
            new Package('foo'),
            new Package('bar')
        );

        $this->assertIsIterable($composer->getPackages());
        $this->assertCount(2, $packages = iterator_to_array($composer->getPackages(), false));
        $this->assertContainsOnlyInstancesOf(Package::class, $packages);
    }

    public function testGetPackages_filter()
    {
        $composer = new Composer('berlioz');
        $composer->addPackage(
            $exceptedPackages = new Package('foo'),
            new Package('bar')
        );

        $packagesFiltered = $composer->getPackages(fn(Package $package) => $package->getName() === 'foo');

        $this->assertIsIterable($packagesFiltered);
        $this->assertCount(1, $packagesFiltered = iterator_to_array($packagesFiltered, false));
        $this->assertSame([$exceptedPackages], $packagesFiltered);
    }

    public function testGetBerliozPackages()
    {
        $exceptedPackages = [];
        $composer = new Composer('berlioz');
        $composer->addPackage(
            $exceptedPackages[] = new Package('foo', type: 'berlioz-package'),
            new Package('bar'),
            $exceptedPackages[] = new Package('baz', type: 'berlioz-package'),
        );

        $this->assertSame($exceptedPackages, iterator_to_array($composer->getBerliozPackages(), false));
    }
}
