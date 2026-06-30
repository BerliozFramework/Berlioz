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

namespace Berlioz\Http\Core\Tests\Http\Middleware;

use Berlioz\Config\Adapter\ArrayAdapter;
use Berlioz\Config\Config;
use Berlioz\Http\Core\Exception\Http\NotFoundHttpException;
use Berlioz\Http\Core\Http\Middleware\DebugConsoleMiddleware;
use Berlioz\Http\Core\Tests\AbstractTestCase;
use Berlioz\Http\Core\Tests\Http\FakeRequestHandler;
use Berlioz\Http\Message\ServerRequest;

class DebugConsoleMiddlewareTest extends AbstractTestCase
{
    private function middleware(array $config): DebugConsoleMiddleware
    {
        return new DebugConsoleMiddleware(new Config([new ArrayAdapter($config)]));
    }

    private function consoleRequest(array $server = []): ServerRequest
    {
        return new ServerRequest('GET', '/_console/abc123', serverParams: $server);
    }

    public function testProcess_letsNonConsolePathsThrough()
    {
        $middleware = $this->middleware(['berlioz' => ['debug' => ['enable' => false]]]);
        $middleware->process(new ServerRequest('GET', '/'), $handler = new FakeRequestHandler());

        $this->assertTrue($handler->isHandled());
    }

    public function testProcess_blocksConsoleWhenDebugDisabled()
    {
        $middleware = $this->middleware(['berlioz' => ['debug' => ['enable' => false]]]);

        $this->expectException(NotFoundHttpException::class);
        $middleware->process($this->consoleRequest(), new FakeRequestHandler());
    }

    public function testProcess_blocksPhpInfoWhenDebugDisabled()
    {
        $middleware = $this->middleware(['berlioz' => ['debug' => ['enable' => false]]]);

        $this->expectException(NotFoundHttpException::class);
        $middleware->process(
            new ServerRequest('GET', '/_console/_phpinfo'),
            new FakeRequestHandler()
        );
    }

    public function testProcess_allowsConsoleWhenDebugEnabledWithoutIpRestriction()
    {
        $middleware = $this->middleware(['berlioz' => ['debug' => ['enable' => true]]]);
        $middleware->process($this->consoleRequest(), $handler = new FakeRequestHandler());

        $this->assertTrue($handler->isHandled());
    }

    public function testProcess_blocksConsoleWhenIpNotAllowed()
    {
        $middleware = $this->middleware([
            'berlioz' => ['debug' => ['enable' => true, 'ip' => ['127.0.0.1']]],
        ]);

        $this->expectException(NotFoundHttpException::class);
        $middleware->process(
            $this->consoleRequest(['REMOTE_ADDR' => '203.0.113.10']),
            new FakeRequestHandler()
        );
    }

    public function testProcess_allowsConsoleWhenIpAllowed()
    {
        $middleware = $this->middleware([
            'berlioz' => ['debug' => ['enable' => true, 'ip' => ['127.0.0.1']]],
        ]);
        $middleware->process(
            $this->consoleRequest(['REMOTE_ADDR' => '127.0.0.1']),
            $handler = new FakeRequestHandler()
        );

        $this->assertTrue($handler->isHandled());
    }

    public function testProcess_doesNotTrustForwardedForWithoutTrustedProxy()
    {
        $middleware = $this->middleware([
            'berlioz' => ['debug' => ['enable' => true, 'ip' => ['127.0.0.1']]],
        ]);

        $this->expectException(NotFoundHttpException::class);
        $middleware->process(
            $this->consoleRequest([
                'REMOTE_ADDR' => '203.0.113.10',
                'HTTP_X_FORWARDED_FOR' => '127.0.0.1',
            ]),
            new FakeRequestHandler()
        );
    }

    public function testProcess_honoursForwardedForBehindTrustedProxy()
    {
        $middleware = $this->middleware([
            'berlioz' => [
                'debug' => ['enable' => true, 'ip' => ['198.51.100.5']],
                'proxies' => ['trusted' => ['203.0.113.0/24']],
            ],
        ]);
        $middleware->process(
            $this->consoleRequest([
                'REMOTE_ADDR' => '203.0.113.10',
                'HTTP_X_FORWARDED_FOR' => '198.51.100.5',
            ]),
            $handler = new FakeRequestHandler()
        );

        $this->assertTrue($handler->isHandled());
    }
}
