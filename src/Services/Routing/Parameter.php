<?php
/**
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2017 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\Core\Services\Routing;


class Parameter
{
    /** @var string Name of parameter */
    private $name;
    /** @var bool Has default value */
    private $hasDefaultValue;
    /** @var mixed Default value */
    private $defaultValue;
    /** @var string Regex validation */
    private $regexValidation;

    /**
     * Parameter constructor.
     */
    public function __construct()
    {
        $this->hasDefaultValue = false;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return static
     */
    public function setName(string $name): Parameter
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Has default value ?
     *
     * @return bool
     */
    public function hasDefaultValue(): bool
    {
        return $this->hasDefaultValue;
    }

    /**
     * Define if has a default value.
     *
     * @param bool $hasDefaultValue
     *
     * @return static
     */
    public function setHasDefaultValue(bool $hasDefaultValue): Parameter
    {
        $this->hasDefaultValue = $hasDefaultValue;

        return $this;
    }

    /**
     * Get default value.
     *
     * @return mixed
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    /**
     * Set default value.
     *
     * @param mixed $defaultValue
     *
     * @return static
     */
    public function setDefaultValue($defaultValue): Parameter
    {
        $this->defaultValue = $defaultValue;

        return $this;
    }

    /**
     * Get regex validation.
     *
     * @return string
     */
    public function getRegexValidation(): string
    {
        return $this->regexValidation ?? '[^/]+';
    }

    /**
     * Set regex validation.
     *
     * @param string $regexValidation
     *
     * @return static
     */
    public function setRegexValidation(string $regexValidation): Parameter
    {
        $this->regexValidation = $regexValidation;

        return $this;
    }
}