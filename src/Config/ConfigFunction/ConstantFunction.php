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

declare(strict_types=1);

namespace Berlioz\Config\ConfigFunction;

use LogicException;

/**
 * Class ConstantFunction.
 */
class ConstantFunction implements ConfigFunctionInterface
{
    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'constant';
    }

    /**
     * @inheritDoc
     */
    public function execute(string $str): mixed
    {
        if (!defined($str)) {
            throw new LogicException(sprintf('Undefined constant "%s" in configuration', $str));
        }

        return constant($str);
    }
}