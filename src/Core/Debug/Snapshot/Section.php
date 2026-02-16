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

namespace Berlioz\Core\Debug\Snapshot;

use Berlioz\Core\Debug\DebugHandler;

/**
 * Interface Section.
 */
interface Section
{
    /**
     * Get section id.
     *
     * @return string
     */
    public function getSectionId(): string;

    /**
     * Get section name.
     *
     * @return string
     */
    public function getSectionName(): string;

    /**
     * Snap.
     */
    public function snap(DebugHandler $debug): void;
}