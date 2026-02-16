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

namespace Berlioz\Cli\Core\Command;

use Attribute;

/**
 * Class Argument.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Argument
{
    public function __construct(
        private string $name,
        private ?string $prefix = null,
        private ?string $longPrefix = null,
        private ?string $description = null,
        private mixed $defaultValue = null,
        private bool $required = false,
        private bool $noValue = false,
        private ?string $castTo = null,
    ) {
    }

    /**
     * Get array copy.
     *
     * @return array
     */
    public function getArrayCopy(): array
    {
        return array_filter(
            [
                'name' => $this->name,
                'prefix' => $this->prefix,
                'longPrefix' => $this->longPrefix,
                'description' => $this->description,
                'defaultValue' => $this->defaultValue,
                'required' => $this->required,
                'noValue' => $this->noValue ?: null,
                'castTo' => $this->castTo,
            ],
            fn($value) => null !== $value
        );
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
     * Get prefix.
     *
     * @return string|null
     */
    public function getPrefix(): ?string
    {
        return $this->prefix;
    }

    /**
     * Get long prefix.
     *
     * @return string|null
     */
    public function getLongPrefix(): ?string
    {
        return $this->longPrefix;
    }

    /**
     * Has prefix?
     *
     * @return bool
     */
    public function hasPrefix(): bool
    {
        return null !== $this->prefix || null !== $this->longPrefix;
    }

    /**
     * Get description.
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Get default value.
     *
     * @return mixed
     */
    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    /**
     * Is required?
     *
     * @return bool
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * has no value?
     *
     * @return bool
     */
    public function hasNoValue(): bool
    {
        return $this->noValue;
    }

    /**
     * Get cast to.
     *
     * @return string|null
     */
    public function getCastTo(): ?string
    {
        return $this->castTo;
    }
}