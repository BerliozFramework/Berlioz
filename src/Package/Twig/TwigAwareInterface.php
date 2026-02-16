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

namespace Berlioz\Package\Twig;

/**
 * Describes a twig-aware instance.
 */
interface TwigAwareInterface
{
    /**
     * Get twig.
     *
     * @return Twig|null
     */
    public function getTwig(): ?Twig;

    /**
     * Set twig.
     *
     * @param Twig $twig
     *
     * @return static
     */
    public function setTwig(Twig $twig): static;
}