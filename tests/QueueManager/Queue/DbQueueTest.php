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

namespace Berlioz\QueueManager\Tests\Queue;

use Berlioz\QueueManager\Queue\DbQueue;
use Berlioz\QueueManager\Queue\QueueInterface;
use Berlioz\QueueManager\RateLimiter\NullRateLimiter;
use Berlioz\QueueManager\RateLimiter\RateLimiterInterface;
use Hector\Connection\Connection;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

#[RequiresPhpExtension('pdo_sqlite')]
#[RequiresMethod(Connection::class, '__construct')]
class DbQueueTest extends QueueTestCase
{
    public static function newQueue(RateLimiterInterface $limiter = new NullRateLimiter()): QueueInterface
    {
        $connection = new Connection('sqlite::memory:');
        $connection->execute(file_get_contents(__DIR__ . '/schema-jobs-sqlite.sql'));

        return new DbQueue(
            connection: $connection,
            name: 'default',
            limiter: $limiter,
        );
    }
}
