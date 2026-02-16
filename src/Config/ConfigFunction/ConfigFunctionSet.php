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

use Berlioz\Config\Exception\ConfigException;

/**
 * Class ConfigFunctionSet.
 */
class ConfigFunctionSet
{
    /** @var ConfigFunctionInterface[] */
    protected array $functions = [];

    public function __construct(array $functions = [])
    {
        $this->add(...$functions);
    }

    /**
     * Get all functions.
     *
     * @return ConfigFunctionInterface[]
     */
    public function all(): array
    {
        return $this->functions;
    }

    /**
     * Add function.
     *
     * @param ConfigFunctionInterface ...$function
     */
    public function add(ConfigFunctionInterface ...$function): void
    {
        foreach ($function as $aFunction) {
            $this->functions[$aFunction->getName()] = $aFunction;
        }
    }

    /**
     * Has function?
     *
     * @param string $name
     *
     * @return bool
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->functions);
    }

    /**
     * Execute.
     *
     * @param string $name
     * @param string $value
     *
     * @return mixed
     * @throws ConfigException
     */
    public function execute(string $name, string $value): mixed
    {
        if (!$this->has($name)) {
            throw new ConfigException(sprintf('Unknown function "%s" in configuration', $name));
        }

        return $this->functions[$name]->execute($value);
    }
}