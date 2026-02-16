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
 * Describes a instantiator-aware instance.
 */
trait InstantiatorAwareTrait
{
    private ?Instantiator $instantiator;

    /**
     * Get instantiator.
     *
     * @return Instantiator|null
     */
    public function getInstantiator(): ?Instantiator
    {
        return $this->instantiator;
    }

    /**
     * Set instantiator.
     *
     * @param Instantiator $instantiator
     *
     * @return static
     */
    public function setInstantiator(Instantiator $instantiator): static
    {
        $this->instantiator = $instantiator;

        return $this;
    }

    /**
     * Has instantiator?
     *
     * @return bool
     */
    public function hasInstantiator(): bool
    {
        return null !== $this->instantiator;
    }
}