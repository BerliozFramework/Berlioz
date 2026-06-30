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

namespace Berlioz\Package\QueueManager\Tests\Handler;

use Berlioz\Core\Core;
use Berlioz\Core\Tests\RestoresErrorHandler;
use Berlioz\Package\QueueManager\Handler\BerliozSystemJobHandler;
use Berlioz\Package\QueueManager\TestProject\TestEnvDirectories;
use Berlioz\Package\QueueManager\Tests\FakeJob;
use PHPUnit\Framework\TestCase;

class BerliozSystemJobHandlerTest extends TestCase
{
    use RestoresErrorHandler;

    private function newCapturingHandler(Core $core): BerliozSystemJobHandler
    {
        return new class($core) extends BerliozSystemJobHandler {
            public string $capturedOutput = '';

            protected function result(false|string $output, int $result): void
            {
                $this->capturedOutput = (string)$output;
            }
        };
    }

    public function testHandle()
    {
        $core = new Core(new TestEnvDirectories(), false);
        $handler = $this->newCapturingHandler($core);

        $handler->handle(new FakeJob(
            'foo',
            'berlioz:system',
            0,
            ['command' => ['echo', 'foo bar']],
        ));

        $this->assertStringContainsString('foo bar', $handler->capturedOutput);
    }

    /**
     * A payload must never be interpreted by a shell: metacharacters are literal arguments.
     */
    public function testHandle_doesNotInterpretShellMetacharacters()
    {
        $core = new Core(new TestEnvDirectories(), false);
        $handler = $this->newCapturingHandler($core);

        // With shell interpretation, the `;` would split the command and run `rm`.
        // Without a shell, the whole string is a single literal argument echoed back verbatim.
        $handler->handle(new FakeJob(
            'foo',
            'berlioz:system',
            0,
            ['command' => ['echo', '; rm -rf /']],
        ));

        $this->assertStringContainsString('; rm -rf /', $handler->capturedOutput);
    }
}
