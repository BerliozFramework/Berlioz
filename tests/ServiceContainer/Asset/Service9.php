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

class Service9
{
    public $param1;
    public $param2;

    public function __construct(Service4|WithoutConstructor|false $param1, bool|int $param2 = true)
    {
        $this->param1 = $param1;
        $this->param2 = $param2;
    }
}