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

use Berlioz\Config\Config;
use LogicException;

/**
 * Class VarFunction.
 */
class VarFunction implements ConfigFunctionInterface
{
    public function __construct(protected Config $config)
    {
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'var';
    }

    /**
     * @inheritDoc
     */
    public function execute(string $str): array|string
    {
        if (false === $this->config->getVariables()->offsetExists($str)) {
            throw new LogicException(sprintf('Undefined variable "%s" in configuration', $str));
        }

        return $this->config->getVariables()->offsetGet($str);
    }
}