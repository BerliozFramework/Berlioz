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


class Service2 implements ServiceInterface
{
    public $param1;
    public $param2;
    public $param3;

    public function __construct(string $param1, Service1 $param2, bool $param3 = true)
    {
        $this->param1 = $param1;
        $this->param2 = $param2;
        $this->param3 = $param3;
    }

    public function test(string $param)
    {
        return sprintf('It\'s a test "%s"', $param);
    }

    public function getParam2()
    {
        return $this->param2;
    }

    public static function testStatic(Service1 $service1)
    {
        return sprintf('It\'s a test "%s"', get_class($service1));
    }
}