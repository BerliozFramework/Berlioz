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

namespace Berlioz\Http\Core\Tests\Http;

use Berlioz\Config\Adapter\ArrayAdapter;
use Berlioz\Config\Config;
use Berlioz\Core\Debug\DebugHandler;
use Berlioz\Http\Core\App\HttpApp;
use Berlioz\Http\Core\Controller\DebugController;
use Berlioz\Http\Core\Exception\Http\NotFoundHttpException;
use Berlioz\Http\Core\Http\Handler\ControllerHandler;
use Berlioz\Http\Core\Http\Middleware\DebugConsoleMiddleware;
use Berlioz\Http\Core\Router\RouterBuilder;
use Berlioz\Http\Message\Response;
use Berlioz\Http\Message\ServerRequest;
use Berlioz\Router\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class DebugConsoleAccessTest.
 */
class DebugConsoleAccessTest extends TestCase
{
    public function testControllerHandlerBlocksObjectTargetWithoutMiddleware(): void
    {
        $this->assertTargetDenied([$this->createMock(DebugController::class), 'phpInfoRaw']);
    }

    public function testControllerHandlerBlocksStringTargetWithoutMiddleware(): void
    {
        $this->assertTargetDenied(DebugController::class . '::phpInfoRaw');
    }

    public function testControllerHandlerBlocksSubclassTargetWithoutMiddleware(): void
    {
        // PHPUnit's generated class extends DebugController.
        $this->assertTargetDenied([$this->createMock(DebugController::class)::class, 'phpInfoRaw']);
    }

    private function assertTargetDenied(array|string $context): void
    {
        $app = $this->createMock(HttpApp::class);
        $app->method('getRoute')->willReturn(new Route('/diagnostics', context: $context));
        $app->method('getConfig')->willReturn(new Config());
        $app->expects($this->never())->method('call');
        $app->expects($this->never())->method('getDebug');

        $this->expectException(NotFoundHttpException::class);
        (new ControllerHandler($app))->handle(new ServerRequest('GET', '/diagnostics'));
    }

    public function testSimilarPrefixRemainsAnOrdinaryRoute(): void
    {
        $config = new Config();
        $response = new Response('ordinary controller');
        $app = $this->createMock(HttpApp::class);
        $app->method('getRoute')->willReturn(new Route('/_console-other', context: static fn() => $response));
        $app->method('getDebug')->willReturn(new DebugHandler());
        $app->expects($this->once())->method('call')->willReturn($response);

        self::assertSame($response, (new DebugConsoleMiddleware($config))->process(
            new ServerRequest('GET', '/_console-other'),
            new ControllerHandler($app),
        ));
    }

    /**
     * @return iterable<string, array{string, bool, bool, string, string}>
     */
    public static function provideRequests(): iterable
    {
        yield 'disabled lowercase' => ['/_console/_phpinfo', false, false, 'GET', 'phpInfoRaw'];
        yield 'disabled uppercase' => ['/_CONSOLE/_phpinfo', false, false, 'GET', 'phpInfoRaw'];
        yield 'disabled mixed case' => ['/_CoNsOlE/_phpinfo', false, false, 'GET', 'phpInfoRaw'];
        yield 'denied IP lowercase' => ['/_console/_phpinfo', true, false, 'GET', 'phpInfoRaw'];
        yield 'denied IP uppercase' => ['/_CONSOLE/_phpinfo', true, false, 'GET', 'phpInfoRaw'];
        yield 'disabled snapshot configuration' => ['/_CONSOLE/abc123/config', false, false, 'GET', 'configuration'];
        yield 'disabled cache purge' => ['/_CONSOLE/abc123/cache', false, false, 'POST', 'cache'];
        yield 'denied IP cache purge' => ['/_CoNsOlE/abc123/cache', true, false, 'POST', 'cache'];
        yield 'disabled alternate route' => ['/diagnostics', false, false, 'GET', 'phpInfoRaw'];
        yield 'denied IP alternate route' => ['/diagnostics', true, false, 'GET', 'phpInfoRaw'];
        yield 'allowed lowercase' => ['/_console/_phpinfo', true, true, 'GET', 'phpInfoRaw'];
        yield 'allowed uppercase' => ['/_CONSOLE/_phpinfo', true, true, 'GET', 'phpInfoRaw'];
        yield 'allowed alternate route' => ['/diagnostics', true, true, 'GET', 'phpInfoRaw'];
    }

    #[DataProvider('provideRequests')]
    public function testResolvedDebugControllerRequiresAuthorization(
        string $path,
        bool $debugEnabled,
        bool $allowed,
        string $method,
        string $controllerMethod,
    ): void {
        $config = new Config([new ArrayAdapter([
            'controllers' => [DebugController::class],
            'berlioz' => ['debug' => ['enable' => $debugEnabled, 'ip' => ['127.0.0.1']]],
        ])]);

        // Build the actual attribute routes, including their requirements and HTTP methods.
        $builder = new RouterBuilder($config);
        $builder->addRoutesFromControllers();
        $router = $builder->getRouter();
        $router->addRoute(new Route('/diagnostics', context: [DebugController::class, 'phpInfoRaw']));

        $request = new ServerRequest($method, $path, serverParams: [
            'REMOTE_ADDR' => $allowed ? '127.0.0.1' : '192.0.2.10',
            // An untrusted peer must not gain access by claiming an allowed address.
            'HTTP_X_FORWARDED_FOR' => '127.0.0.1',
        ]);
        if ('POST' === $method) {
            $request = $request->withParsedBody(['clear' => 'all']);
        }

        $route = $router->handle($request);
        self::assertNotNull($route);
        self::assertSame([DebugController::class, $controllerMethod], $route->getContext());

        $app = $this->createMock(HttpApp::class);
        $app->method('getRoute')->willReturn($route);
        $app->method('getConfig')->willReturn($config);
        $app->method('getDebug')->willReturn(new DebugHandler());

        // Intercept the invocation boundary: never generate phpinfo or actually purge caches.
        $invoked = false;
        $app->method('call')->willReturnCallback(static function () use (&$invoked): Response {
            $invoked = true;

            return new Response('controller reached');
        });

        $middleware = new DebugConsoleMiddleware($config);
        $denied = false;
        $response = null;
        try {
            $response = $middleware->process($request, new ControllerHandler($app));
        } catch (NotFoundHttpException) {
            $denied = true;
        }

        self::assertSame($allowed, $invoked, 'Unauthorized requests must stop before controller invocation.');
        self::assertSame(!$allowed, $denied);
        if ($allowed) {
            self::assertNotNull($response);
            self::assertSame('controller reached', (string)$response->getBody());
        }
    }
}
