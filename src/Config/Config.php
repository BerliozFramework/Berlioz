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

namespace Berlioz\Config;

use ArrayObject;
use Berlioz\Config\Adapter\AdapterInterface;
use Berlioz\Config\Exception\ConfigException;

/**
 * Class Config.
 */
class Config implements ConfigInterface
{
    protected const ENCAPSULATION_START = '{';
    protected const ENCAPSULATION_END = '}';

    protected array $configs = [];
    protected ArrayObject $variables;
    protected ConfigFunction\ConfigFunctionSet $functions;

    /**
     * Config constructor.
     *
     * @param AdapterInterface[] $configs
     * @param array $variables
     */
    public function __construct(
        array $configs = [],
        array $variables = [],
    ) {
        $this->addConfig(...$configs);
        $this->variables = new ArrayObject($variables);

        $this->functions = new ConfigFunction\ConfigFunctionSet(
            [
                new ConfigFunction\ConfigFunction($this),
                new ConfigFunction\ConstantFunction(),
                new ConfigFunction\EnvFunction(),
                new ConfigFunction\FileFunction(),
                new ConfigFunction\VarFunction($this),
            ]
        );
    }

    /**
     * Get variables.
     *
     * @return ArrayObject
     */
    public function getVariables(): ArrayObject
    {
        return $this->variables;
    }

    /**
     * Add functions.
     *
     * @param ConfigFunction\ConfigFunctionInterface ...$function
     */
    public function addFunction(ConfigFunction\ConfigFunctionInterface ...$function): void
    {
        $this->functions->add(...$function);
    }

    /**
     * Get all configurations.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->configs;
    }

    /**
     * Add config.
     *
     * @param AdapterInterface ...$config
     */
    public function addConfig(AdapterInterface ...$config): void
    {
        array_unshift($this->configs, ...$config);
        usort($this->configs, fn($config1, $config2) => $config2->getPriority() <=> $config1->getPriority());
    }

    /**
     * Get value or fail.
     *
     * Key given in parameter must be in format: key.key2.key3
     *
     * @param string $key
     *
     * @return mixed
     * @throws ConfigException
     */
    public function getOrFail(string $key): mixed
    {
        if (!$this->has($key)) {
            throw new ConfigException(sprintf('Missing configuration value at "%s"', $key));
        }

        return $this->get($key) ?? throw new ConfigException(sprintf('Missing configuration value at "%s"', $key));
    }

    /**
     * @inheritDoc
     */
    public function get(?string $key = null, mixed $default = null): mixed
    {
        if (null === $key) {
            return $this->getArrayCopy(true);
        }

        $arrayValue = null;
        $found = false;

        foreach ($this->configs as $config) {
            if (false === $config->has($key)) {
                continue;
            }

            // Get value
            $value = $config->get($key);
            $found = true;

            // Not an array, so not necessary to merge or continue
            if (!is_array($value)) {
                // If back value is an array, so can't merge values
                if (null !== $arrayValue) {
                    $this->treatValue($arrayValue);

                    return $arrayValue;
                }

                $this->treatValue($value);

                return $value;
            }

            $arrayValue = b_array_merge_recursive($value, $arrayValue ?? []);
        }

        if (false === $found) {
            return $default;
        }

        $value = $arrayValue;
        $this->treatValue($value);

        return $value;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        foreach ($this->configs as $config) {
            if ($config->has($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function getArrayCopy(bool $compiled = false): array
    {
        if (empty($this->configs)) {
            return [];
        }

        $configArrays = array_map(fn(ConfigInterface $config) => $config->getArrayCopy(), $this->configs);
        $configArrays = array_reverse($configArrays);
        $configArray = b_array_merge_recursive(...$configArrays);
        unset($configArrays);

        if (false === $compiled) {
            return $configArray;
        }

        $this->treatValue($configArray);

        return $configArray;
    }

    /**
     * Treat value.
     *
     * @param mixed $value
     *
     * @throws Exception\ConfigException
     */
    protected function treatValue(mixed &$value): void
    {
        // Not an array or string
        if (!is_array($value) && !is_string($value)) {
            return;
        }

        // Treat recursive values
        if (is_array($value)) {
            array_walk_recursive($value, [$this, 'treatValue']);
            return;
        }

        $matches = [];
        if (!preg_match_all(
            '#' . static::ENCAPSULATION_START . '\s*(?:=|(?<function>\w+)\s*:\s*)(?<value>[^}]+)\s*' . static::ENCAPSULATION_END . '#',
            $value,
            $matches,
            PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL
        )) {
            return;
        }

        if (empty($matches)) {
            return;
        }

        $shift = 0;
        foreach ($matches[0] as $key => $match) {
            $function = $matches['function'][$key][0] ?? 'var';
            $result = $this->functions->execute($function, trim($matches['value'][$key][0]));

            if (strlen($match[0]) == strlen($value)) {
                $value = $result;
                return;
            }

            $result = (string)$result;
            $value = substr_replace($value, $result, $match[1] + $shift, $length = strlen($match[0]));
            $shift += strlen($result) - $length;
        }
    }
}
