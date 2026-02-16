<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2024 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\QueueManager\Exception;

class QueueException extends QueueManagerException
{
    /**
     * Checksum validation failed.
     *
     * @return static
     */
    public static function checksum(): static
    {
        return new self('Checksum mismatch');
    }

    /**
     * Bad job.
     *
     * @param string $actual
     * @param string $expected
     *
     * @return static
     */
    public static function badJob(string $actual, string $expected): static
    {
        return new self(sprintf('Expected job type `%s`, actual `%s`', $expected, $actual));
    }
}
