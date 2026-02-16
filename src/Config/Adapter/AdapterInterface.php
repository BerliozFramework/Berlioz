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

namespace Berlioz\Config\Adapter;

use Berlioz\Config\ConfigInterface;

/**
 * Interface AdapterInterface.
 */
interface AdapterInterface extends ConfigInterface
{
    /**
     * Get priority of configuration.
     *
     * Values are gotten on high priority config first.
     *
     * @return int
     */
    public function getPriority(): int;

    /**
     * Set priority of configuration.
     *
     * @param int $priority
     */
    public function setPriority(int $priority): void;
}