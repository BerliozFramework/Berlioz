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

namespace Berlioz\Core\Tests;

use Berlioz\Core\Core;
use Berlioz\Core\CoreAwareTrait;
use Berlioz\Core\Tests\Directories\FakeDefaultDirectories;
use PHPUnit\Framework\TestCase;

class CoreAwareTraitTest extends TestCase
{
    use RestoresErrorHandler;

    public function test()
    {
        $object = new class {
            use CoreAwareTrait;
        };

        $this->assertNull($object->getCore());

        $object->setCore($core = new Core(new FakeDefaultDirectories(), false));

        $this->assertSame($object->getCore(), $core);
    }
}
