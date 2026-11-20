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

namespace Berlioz\QueueManager\Tests\Job;

use Berlioz\QueueManager\Job\Job;
use Berlioz\QueueManager\Queue\NullQueue;
use Berlioz\QueueManager\Queue\QueueInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class JobTest extends TestCase
{
    private function newJob(int $attempts, ?int $maxAttempts): Job
    {
        return new class ('id', 'name', $attempts, [], $maxAttempts) extends Job {
            public function getQueue(): QueueInterface
            {
                return new NullQueue();
            }
        };
    }

    public static function provideIsLastAttempt(): array
    {
        return [
            'without max attempts' => [1, null, false],
            'before last attempt' => [2, 3, false],
            'last attempt' => [3, 3, true],
            'beyond last attempt' => [4, 3, true],
            'single attempt' => [1, 1, true],
        ];
    }

    #[DataProvider('provideIsLastAttempt')]
    public function testIsLastAttempt(int $attempts, ?int $maxAttempts, bool $expected): void
    {
        $job = $this->newJob($attempts, $maxAttempts);

        $this->assertSame($maxAttempts, $job->getMaxAttempts());
        $this->assertSame($expected, $job->isLastAttempt());
    }
}
