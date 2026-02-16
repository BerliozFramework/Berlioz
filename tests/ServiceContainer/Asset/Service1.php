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


class Service1
{
    public $param1;
    public $param2;
    public $param3;

    public function __construct(string $param1, string $param2, int $param3)
    {
        $this->param1 = $param1;
        $this->param2 = $param2;
        $this->param3 = $param3;
    }

    public function increaseParam3(int $nb = 1)
    {
        $this->param3 += $nb;
    }

    public function getParam1(): string
    {
        return $this->param1;
    }

    public function getParam3(): int
    {
        return $this->param3;
    }
}