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

namespace Berlioz\ServiceContainer;

/**
 * Describes a container-aware instance.
 */
trait ContainerAwareTrait
{
    private ?Container $container = null;

    /**
     * Get container.
     *
     * @return Container|null
     */
    public function getContainer(): ?Container
    {
        return $this->container;
    }

    /**
     * Set container.
     *
     * @param Container $container
     *
     * @return static
     */
    public function setContainer(Container $container): static
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Has container?
     *
     * @return bool
     */
    public function hasContainer(): bool
    {
        return null !== $this->container;
    }
}