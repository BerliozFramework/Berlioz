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

namespace Berlioz\Package\QueueManager\Tests\Fake;

use Berlioz\QueueManager\Handler\JobHandlerInterface;
use Berlioz\QueueManager\Job\JobInterface;
use RuntimeException;

class FakeJobHandler implements JobHandlerInterface
{
    public static array $handled = [];

    public function handle(JobInterface $job): void
    {
        if (true === $job->getPayload()->get('fail', false)) {
            throw new RuntimeException('Job failed');
        }

        self::$handled[] = $job->getPayload()->get('id');
    }
}
