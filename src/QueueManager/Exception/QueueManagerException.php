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

use Exception;

class QueueManagerException extends Exception
{
    /**
     * Missing package.
     *
     * @param string $package
     *
     * @return static
     */
    public static function missingPackage(string $package): static
    {
        return new self(sprintf('Package `%s` was not found.', $package));
    }

    /**
     * Queue not found.
     *
     * @param string ...$queueName
     *
     * @return static
     */
    public static function queueNotFound(string ...$queueName): static
    {
        $queueName = array_map(fn($v) => sprintf('`%s`', $v), $queueName);
        $plural = count($queueName) > 1;

        return new self(sprintf(
            'Queue%2$s %1$s not found%2$s',
            implode(', ', $queueName),
            $plural ? 's' : ''
        ));
    }

    /**
     * Invalid JobHandler.
     *
     * @param string $jobName
     *
     * @return static
     */
    public static function invalidJobHandler(string $jobName): static
    {
        return new self(sprintf('Invalid job handler for job `%s`.', $jobName));
    }
}
