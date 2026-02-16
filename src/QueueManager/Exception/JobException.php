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

use Berlioz\QueueManager\Job\JobInterface;

class JobException extends QueueException
{
    /**
     * Job already released.
     *
     * @param JobInterface $job
     *
     * @return static
     */
    public static function alreadyReleased(JobInterface $job): static
    {
        return new self(message: sprintf('Job #%s has already been released', $job->getId()));
    }

    /**
     * Job already deleted.
     *
     * @param JobInterface $job
     *
     * @return static
     */
    public static function alreadyDeleted(JobInterface $job): static
    {
        return new self(message: sprintf('Job #%s has already been deleted', $job->getId()));
    }
}
