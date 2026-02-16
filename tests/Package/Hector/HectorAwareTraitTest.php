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

namespace Berlioz\Package\Hector\Tests;

use Berlioz\Package\Hector\HectorAwareTrait;

class HectorAwareTraitTest extends AbstractTestCase
{
    public function test()
    {
        $object = new class {
            use HectorAwareTrait;
        };

        $this->assertNull($object->getOrm());

        $object->setOrm($orm = FakeOrm::get());

        $this->assertSame($object->getOrm(), $orm);
    }
}
