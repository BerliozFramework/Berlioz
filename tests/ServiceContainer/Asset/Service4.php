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

namespace Berlioz\ServiceContainer\Tests\Asset;


class Service4
{
    public $param1;
    public $param2;
    public $param3;
    public $param4;

    public function test()
    {
        return sprintf('It\'s a test "%s"', static::class);
    }

    public static function testStatic()
    {
        return sprintf('It\'s a test "%s"', static::class);
    }
}