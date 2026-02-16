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

namespace Berlioz\Core;

/**
 * Trait CoreAwareTrait.
 */
trait CoreAwareTrait
{
    protected Core $core;

    /**
     * Get core.
     *
     * @return Core|null
     */
    public function getCore(): ?Core
    {
        return $this->core ?? null;
    }

    /**
     * Set core.
     *
     * @param Core $core
     *
     * @return static
     */
    public function setCore(Core $core): static
    {
        $this->core = $core;

        return $this;
    }
}