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

namespace Berlioz\Cli\Core\Tests\App;

use Berlioz\Cli\Core\App\CliApp;
use Berlioz\Cli\Core\App\CliAppAwareTrait;
use Berlioz\Cli\Core\Tests\FakeDefaultDirectories;
use Berlioz\Core\Core;
use Berlioz\Core\Tests\RestoresErrorHandler;
use PHPUnit\Framework\TestCase;

class CliAppAwareTraitTest extends TestCase
{
    use RestoresErrorHandler;

    public function test()
    {
        $app = new CliApp(new Core(new FakeDefaultDirectories(), false));
        $trait = new class {
            use CliAppAwareTrait;
        };
        $trait->setApp($app);

        $this->assertTrue($trait->hasApp());
        $this->assertSame($trait->getApp(), $app);
    }

    public function testEmpty()
    {
        $trait = new class {
            use CliAppAwareTrait;
        };

        $this->assertFalse($trait->hasApp());
        $this->assertNull($trait->getApp());
    }
}
