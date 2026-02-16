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

namespace Berlioz\ServiceContainer\Tests\Inflector;

use Berlioz\ServiceContainer\Inflector\Inflector;
use PHPUnit\Framework\TestCase;

class InflectorTest extends TestCase
{
    public function test()
    {
        $inflector = new Inflector(
            $interface = 'INTERFACE',
            $method = 'METHOD',
            $arguments = ['foo' => 'bar', 'baz' => 'qux']
        );

        $this->assertEquals($interface, $inflector->getInterface());
        $this->assertEquals($method, $inflector->getMethod());
        $this->assertEquals($arguments, $inflector->getArguments());
    }
}
