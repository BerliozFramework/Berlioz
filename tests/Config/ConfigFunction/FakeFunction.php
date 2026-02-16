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

namespace Berlioz\Config\Tests\ConfigFunction;

use Berlioz\Config\ConfigFunction\ConfigFunctionInterface;

class FakeFunction implements ConfigFunctionInterface
{
    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'fake';
    }

    /**
     * @inheritDoc
     */
    public function execute(string $str): mixed
    {
        return $str;
    }
}