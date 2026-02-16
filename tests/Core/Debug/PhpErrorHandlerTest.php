<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2021 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\Core\Tests\Debug;

use Berlioz\Core\Debug\PhpErrorHandler;
use Berlioz\Core\Debug\Snapshot\PhpError;
use PHPUnit\Framework\TestCase;

class PhpErrorHandlerTest extends TestCase
{
    public function test()
    {
        $handler = new PhpErrorHandler();

        $this->assertCount(0, $handler->getErrors());

        $error1 = ['errno' => 1, 'message' => 'Foo', 'file' => 'bar.php', 'line' => 123];
        $error2 = ['errno' => 2, 'message' => 'Bar', 'file' => 'foo.php', 'line' => 321];

        $handler->handler(...$error1);
        $handler->handler(...$error2);

        $this->assertCount(2, $handler->getErrors());
        $this->assertEquals(unserialize(serialize($handler)), $handler);

        $handler->handle();
        $line = __LINE__;
        @trigger_error('Qux', E_USER_NOTICE);
        restore_error_handler();

        $this->assertCount(3, $handler->getErrors());

        $phpErrors = iterator_to_array($handler->getErrors()->getErrors(), true);
        /** @var PhpError $lastError */
        $lastError = end($phpErrors);
        $this->assertEquals(
            ['errno' => E_USER_NOTICE, 'message' => 'Qux', 'file' => __FILE__, 'line' => $line + 1],
            [
                'errno' => $lastError->getErrno(),
                'message' => $lastError->getMessage(),
                'file' => $lastError->getFile(),
                'line' => $lastError->getLine()
            ]
        );
    }
}
