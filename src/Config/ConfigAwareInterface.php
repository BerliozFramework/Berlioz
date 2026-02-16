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

/**
 * Describes a config-aware instance.
 */
interface ConfigAwareInterface
{
    /**
     * Get config.
     *
     * @return ConfigInterface|null
     */
    public function getConfig(): ?ConfigInterface;

    /**
     * Set config.
     *
     * @param ConfigInterface $config
     *
     * @return static
     */
    public function setConfig(ConfigInterface $config): static;

    /**
     * Has config?
     *
     * @return bool
     */
    public function hasConfig(): bool;
}