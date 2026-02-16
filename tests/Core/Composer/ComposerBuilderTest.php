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
use Berlioz\Core\Composer\ComposerBuilder;
use Berlioz\Core\Filesystem\BerliozFilesystem;
use Berlioz\Core\Tests\Directories\FakeDefaultDirectories;
use Error;
use PHPUnit\Framework\TestCase;

class ComposerBuilderTest extends TestCase
{
    public function testReset()
    {
        $builder = new ComposerBuilder(new BerliozFilesystem(new FakeDefaultDirectories()));
        $builder->build();
        $composer = $builder->getComposer();

        $this->assertInstanceOf(Composer::class, $composer);
        $this->expectException(Error::class);

        $builder->reset();
        $builder->getComposer();
    }

    public function testBuild()
    {
        $builder = new ComposerBuilder(new BerliozFilesystem(new FakeDefaultDirectories()));
        $builder->build();
        $composer = $builder->getComposer();

        $this->assertInstanceOf(Composer::class, $composer);
        $this->assertCount(2, iterator_to_array($composer->getBerliozPackages()));
        $this->assertCount(9, iterator_to_array($composer->getPackages()));

        $this->assertEquals('berlioz/fake1', $composer->getBerliozPackages()->current()->getName());
        $this->assertEquals('Berlioz Fake Package #1', $composer->getBerliozPackages()->current()->getDescription());

        $this->assertEquals('berlioz/config', $composer->getPackages()->current()->getName());
        $this->assertEquals('Berlioz Configuration', $composer->getPackages()->current()->getDescription());
    }
}
