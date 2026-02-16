<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2025 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\QueueManager\RateLimiter;

use InvalidArgumentException;

readonly class RateLimitParser
{
    /**
     * Parse rate string.
     *
     * @param string $str
     *
     * @return int[]
     */
    public static function parse(string $str): array
    {
        $regex = '/^\s*(?<limit>\d+)\s*\/\s*(?<multiplier>\d+)?\s*(?<unit>[a-z]+)\s*$/i';

        if (1 !== preg_match($regex, $str, $matches)) {
            throw new InvalidArgumentException(sprintf('Invalid time rate limit "%s"', $str));
        }

        $limit = (int)$matches['limit'];
        $unit = strtolower($matches['unit']);
        $multiplier = (int)max($matches['multiplier'] ?? 1, 1);

        return match ($unit) {
            's',
            'sec',
            'secs',
            'second',
            'seconds' => [$limit, 1 * $multiplier],
            'm',
            'min',
            'mins',
            'minute',
            'minutes' => [$limit, 60 * $multiplier],
            'h',
            'hour',
            'hours' => [$limit, 3600 * $multiplier],
            'd',
            'day',
            'days' => [$limit, 86400 * $multiplier],
            default => throw new InvalidArgumentException(sprintf('Invalid time rate limit unit "%s"', $unit))
        };
    }
}
