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

namespace Berlioz\Core\Composer;

/**
 * Class Package.
 */
class Package
{
    public const DEFAULT_TYPE = 'library';

    public function __construct(
        protected string $name,
        protected ?string $version = null,
        protected string $type = self::DEFAULT_TYPE,
        protected ?string $description = null,
        protected array $config = [],
    ) {
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
     * Get version.
     *
     * @return string|null
     */
    public function getVersion(): ?string
    {
        return $this->version;
    }

    /**
     * Get type.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
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
     * Get config.
     *
     * @param string|null $path
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function getConfig(?string $path = null, mixed $default = null): mixed
    {
        if (null === $path) {
            return $this->config;
        }

        return b_array_traverse_get($this->config, $path, $default);
    }
}