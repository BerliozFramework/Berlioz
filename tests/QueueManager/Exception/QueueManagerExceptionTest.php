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

namespace Berlioz\QueueManager\Tests\Exception;

use Berlioz\QueueManager\Exception\QueueManagerException;
use PHPUnit\Framework\TestCase;

class QueueManagerExceptionTest extends TestCase
{
    public function testQueueNotFoundSingularMessage(): void
    {
        $exception = QueueManagerException::queueNotFound('default');

        $this->assertSame('Queue `default` not found.', $exception->getMessage());
    }

    public function testQueueNotFoundPluralMessage(): void
    {
        $exception = QueueManagerException::queueNotFound('foo', 'bar');

        $this->assertSame('Queues `foo`, `bar` not found.', $exception->getMessage());
    }
}
