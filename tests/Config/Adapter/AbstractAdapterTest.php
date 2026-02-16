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

namespace Berlioz\Config\Tests\Adapter;

use Berlioz\Config\Adapter\JsonAdapter;
use PHPUnit\Framework\TestCase;

class AbstractAdapterTest extends TestCase
{
    public function testGetPriority()
    {
        $adapter = new JsonAdapter(str: '{}', priority: 1);

        $this->assertEquals(1, $adapter->getPriority());
    }

    public function testGetPriorityDefault()
    {
        $adapter = new JsonAdapter(str: '{}');

        $this->assertEquals(0, $adapter->getPriority());
    }
}
