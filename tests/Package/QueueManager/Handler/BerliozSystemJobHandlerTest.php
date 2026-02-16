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

    public function testHandle()
    {
        $core = new Core(new TestEnvDirectories(), false);
        $handler = new class($core) extends BerliozSystemJobHandler {
            protected function result(false|string $output, int $result): void
            {
                print $output;
                print getcwd();
            }
        };

        ob_start();
        $handler->handle(new FakeJob(
            'foo',
            'berlioz:system',
            0,
            ['command' => ['echo "foo bar"']],
        ));
        $output = ob_get_clean();

        $this->assertStringContainsString('foo bar', $output);
        $this->assertStringEndsWith($core->getDirectories()->getAppDir(), $output);
    }
}
