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

namespace Berlioz\Cli\Core\App;

/**
 * Trait CliAppAwareTrait.
 */
trait CliAppAwareTrait
{
    private CliApp $app;

    /**
     * Get application.
     *
     * @return CliApp|null
     */
    public function getApp(): ?CliApp
    {
        return $this->app ?? null;
    }

    /**
     * Set application.
     *
     * @param CliApp $app
     *
     * @return static
     */
    public function setApp(CliApp $app): static
    {
        $this->app = $app;

        return $this;
    }

    /**
     * Has application?
     *
     * @return bool
     */
    public function hasApp(): bool
    {
        return null !== ($this->app ?? null);
    }
}