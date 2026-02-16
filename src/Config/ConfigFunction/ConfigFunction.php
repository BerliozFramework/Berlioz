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
 * Class ConfigFunction.
 */
class ConfigFunction implements ConfigFunctionInterface
{
    public function __construct(protected Config $config)
    {
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'config';
    }

    /**
     * @inheritDoc
     */
    public function execute(string $str): mixed
    {
        $value = $this->config->get($str, new LogicException(sprintf('Config path "%s" does not exists', $str)));

        if ($value instanceof LogicException) {
            throw $value;
        }

        return $value;
    }
}