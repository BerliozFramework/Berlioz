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

namespace Berlioz\Http\Core\Tests\Http\Middleware;

use Berlioz\Http\Core\Http\Middleware\ForwardedPrefixMiddleware;
use Berlioz\Http\Core\Tests\AbstractTestCase;
use Berlioz\Http\Core\Tests\Http\FakeRequestHandler;
use Berlioz\Http\Message\Response;
use Berlioz\Http\Message\ServerRequest;
use Berlioz\Router\ForwardedPrefixResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

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
        $app = $this->getApp();
        $middleware = new ForwardedPrefixMiddleware($app, $app->get(ForwardedPrefixResolver::class));

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
        $app = $this->getApp();
        $middleware = new ForwardedPrefixMiddleware($app, $app->get(ForwardedPrefixResolver::class));

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
        $app = $this->getApp();
        $middleware = new ForwardedPrefixMiddleware($app, $app->get(ForwardedPrefixResolver::class));
        $handler = new FakeRequestHandler();

        $request = $this->request('/articles', ['REMOTE_ADDR' => '10.0.0.1']);
        $middleware->process($request, $handler);

        $this->assertTrue($handler->isHandled());
    }

    public static function provideInternalPaths(): array
    {
        return [
            'different segment' => ['/articles', '/app/articles'],
            'same segment' => ['/app/articles', '/app/app/articles'],
            'exact mount' => ['/app', '/app/app'],
            'similar segment' => ['/application', '/app/application'],
            'root' => ['/', '/app/'],
        ];
    }

    public function testProcess_invalidPrefixPreservesRequest(): void
    {
        $app = $this->getApp();
        $request = $this->request('/articles?page=2', [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_PREFIX' => '/app%23fragment',
        ]);
        $app->setRequest($request);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->with($this->identicalTo($request))
            ->willReturn(new Response());

        $middleware = new ForwardedPrefixMiddleware($app, $app->get(ForwardedPrefixResolver::class));
        $middleware->process($request, $handler);

        $this->assertSame($request, $app->getRequest());
        $this->assertNull($request->getAttribute(ForwardedPrefixMiddleware::REQUEST_ATTRIBUTE));
    }

    public function testProcess_exposesResolvedEncodedPrefix(): void
    {
        $app = $this->getApp();
        $request = $this->request('/articles?page=2', [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_PREFIX' => '//my%20app///',
        ]);

        $middleware = new ForwardedPrefixMiddleware($app, $app->get(ForwardedPrefixResolver::class));
        $middleware->process($request, new FakeRequestHandler());

        $this->assertSame(
            '/my%20app',
            $app->getRequest()->getAttribute(ForwardedPrefixMiddleware::REQUEST_ATTRIBUTE),
        );
        $this->assertSame('http://example.com/my%20app/articles?page=2', (string)$app->getRequest()->getUri());
    }

    #[DataProvider('provideInternalPaths')]
    public function testProcess_rewritesOnlyOnce(string $path, string $expectedPath): void
    {
        $app = $this->getApp();
        $middleware = new ForwardedPrefixMiddleware($app, $app->get(ForwardedPrefixResolver::class));
        $request = $this->request($path . '?page=2', [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_PREFIX' => '/app',
        ]);
        $captured = null;
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->exactly(2))->method('handle')->willReturnCallback(
            function (ServerRequestInterface $request) use (&$captured): ResponseInterface {
                $captured = $request;

                return new Response();
            },
        );

        $middleware->process($request, $handler);
        $rewritten = $captured;
        $this->assertSame($expectedPath, $rewritten->getUri()->getPath());
        $this->assertSame('page=2', $rewritten->getUri()->getQuery());
        $this->assertSame('/app', $rewritten->getAttribute(ForwardedPrefixMiddleware::REQUEST_ATTRIBUTE));
        $this->assertSame($rewritten, $app->getRequest());
        $this->assertSame($path, $request->getUri()->getPath());

        // A new middleware instance must also recognize the rewritten request.
        $secondMiddleware = new ForwardedPrefixMiddleware($app, $app->get(ForwardedPrefixResolver::class));
        $secondMiddleware->process($rewritten, $handler);

        $this->assertSame($rewritten, $captured);
        $this->assertSame($rewritten, $app->getRequest());
    }
}
