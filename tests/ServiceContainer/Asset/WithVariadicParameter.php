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

class WithVariadicParameter
{
    public array $variadic;

    public function __construct(public WithoutConstructor $param, string ...$variadic)
    {
        $this->variadic = $variadic;
    }
}