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

namespace Berlioz\QueueManager\Handler;

use Berlioz\QueueManager\Exception\QueueManagerException;
use Berlioz\QueueManager\Job\JobInterface;

interface JobHandlerInterface
{
    /**
     * Handle job.
     *
     * @param JobInterface $job
     *
     * @return void
     * @throws QueueManagerException
     */
    public function handle(JobInterface $job): void;
}
