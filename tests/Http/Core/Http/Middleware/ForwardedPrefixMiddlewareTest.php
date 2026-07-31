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

use Berlioz\Http\Core\Http\Middleware\ForwardedPrefixMiddleware;
use Berlioz\Http\Core\Tests\AbstractTestCase;
use Berlioz\Http\Core\Tests\Http\FakeRequestHandler;
use Berlioz\Http\Message\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ForwardedPrefixMiddlewareTest extends AbstractTestCase
{
    private function request(string $path, array $serverParams): ServerRequest
    {
        return new ServerRequest(
            'GET',
            'http://example.com' . $path,
            serverParams: $serverParams,
        );
    }

    public function testProcess_prefixesUri_fromTrustedProxy()
    {
        $middleware = new ForwardedPrefixMiddleware($app = $this->getApp());

        $captured = null;
        $handler = new FakeRequestHandler();
        $request = $this->request('/articles', [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_PREFIX' => '/app',
        ]);

        // Capture the request forwarded to the next handler.
        $handler = new class ($captured) extends FakeRequestHandler {
            public function __construct(public ?ServerRequestInterface &$captured)
            {
                parent::__construct();
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->captured = $request;

                return parent::handle($request);
            }
        };

        $middleware->process($request, $handler);

        $this->assertSame('/app/articles', $handler->captured->getUri()->getPath());
        $this->assertSame('/app', $handler->captured->getAttribute(ForwardedPrefixMiddleware::REQUEST_ATTRIBUTE));
        // HttpApp::$request must be kept in sync.
        $this->assertSame('/app/articles', $app->getRequest()->getUri()->getPath());
    }

    public function testProcess_untrustedProxy_isNoOp()
    {
        $middleware = new ForwardedPrefixMiddleware($this->getApp());

        $handler = new class extends FakeRequestHandler {
            public ?ServerRequestInterface $captured = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->captured = $request;

                return parent::handle($request);
            }
        };

        $request = $this->request('/articles', [
            'REMOTE_ADDR' => '203.0.113.7',
            'HTTP_X_FORWARDED_PREFIX' => '/app',
        ]);

        $middleware->process($request, $handler);

        $this->assertSame('/articles', $handler->captured->getUri()->getPath());
        $this->assertNull($handler->captured->getAttribute(ForwardedPrefixMiddleware::REQUEST_ATTRIBUTE));
    }

    public function testProcess_noHeader_isNoOp()
    {
        $middleware = new ForwardedPrefixMiddleware($this->getApp());
        $handler = new FakeRequestHandler();

        $request = $this->request('/articles', ['REMOTE_ADDR' => '10.0.0.1']);
        $middleware->process($request, $handler);

        $this->assertTrue($handler->isHandled());
    }
}

