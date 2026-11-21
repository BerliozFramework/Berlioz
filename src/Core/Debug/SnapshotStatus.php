<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Core\Debug;

/**
 * Enum SnapshotStatus.
 */
enum SnapshotStatus: string
{
    /** Snapshot saved, ready to be loaded */
    case READY = 'ready';
    /** Snapshot still in progress (e.g. work done after the response) */
    case PENDING = 'pending';
    /** Snapshot never saved before the pending marker expiration (process interrupted?) */
    case ABANDONED = 'abandoned';
    /** Unknown snapshot */
    case MISSING = 'missing';
}
