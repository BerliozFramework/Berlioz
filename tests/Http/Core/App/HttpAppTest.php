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

declare(strict_types=1);

namespace Berlioz\Http\Core\Tests\App;

use Berlioz\Config\Adapter\ArrayAdapter;
use Berlioz\Core\Core;
use Berlioz\Core\Tests\RestoresErrorHandler;
use Berlioz\Http\Core\App\HttpApp;
use Berlioz\Http\Core\App\Maintenance;
use Berlioz\Http\Core\TestProject\Controller\ControllerOne;
use Berlioz\Http\Core\TestProject\FakeDefaultDirectories;
use Berlioz\Http\Core\TestProject\Http\Middleware\AbstractMiddleware;
use Berlioz\Http\Core\TestProject\Http\Middleware\BarMiddleware;
use Berlioz\Http\Core\TestProject\Http\Middleware\BazMiddleware;
use Berlioz\Http\Core\TestProject\Http\Middleware\FooMiddleware;
use Berlioz\Http\Core\TestProject\Http\Middleware\QuxMiddleware;
use Berlioz\Http\Message\Response;
use Berlioz\Http\Message\ServerRequest;
use Berlioz\Router\ForwardedPrefixResolver;
use Berlioz\Router\Route;
use Berlioz\Router\Router;
use Berlioz\Router\RouterInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HttpAppTest extends TestCase
{
    use RestoresErrorHandler;

    public function test__construct()
    {
        $app = new HttpApp($core = new Core(new FakeDefaultDirectories(), false));

        $this->assertInstanceOf(HttpApp::class, $app);
        $this->assertSame($app->getCore(), $core);
    }

    public function testGetMaintenance_disabled()
    {
        $app = new HttpApp(new Core(new FakeDefaultDirectories(), false));

        $this->assertNull($app->getMaintenance());
    }

    public function testGetMaintenance_enabled()
    {
        $core = new Core(new FakeDefaultDirectories(), false);
        $core->getConfig()->addConfig(new ArrayAdapter(['berlioz' => ['maintenance' => true]]));
        $app = new HttpApp($core);

        $this->assertInstanceOf(Maintenance::class, $app->getMaintenance());
    }

    public function testGetRouter()
    {
        $app = new HttpApp(new Core(new FakeDefaultDirectories(), false));

        $this->assertInstanceOf(RouterInterface::class, $app->getRouter());
        $this->assertNotEmpty(iterator_to_array($app->getRouter()->getRoutes()));
    }

    public function testGetRequest()
    {
        $app = new HttpApp(new Core(new FakeDefaultDirectories(), false));
        $app->handle($request = new ServerRequest('GET', 'https://getberlioz.com'));

        $this->assertSame($request, $app->getRequest());
    }

    public function testGetRoute()
    {
        $app = new HttpApp(new Core(new FakeDefaultDirectories(), false));
        $app->handle(new ServerRequest('GET', '/controller1/method1'));

        $this->assertInstanceOf(Route::class, $app->getRoute());
        $this->assertEquals(
            [
                ControllerOne::class,
                'methodOne'
            ],
            $app->getRoute()->getContext()
        );
    }

    public function testGetRoute_NULL()
    {
        $app = new HttpApp(new Core(new FakeDefaultDirectories(), false));
        $app->handle(new ServerRequest('GET', '/unknown'));

        $this->assertNull($app->getRoute());
    }

    public function testHandle_withForwardedPrefix_fromTrustedProxy()
    {
        $core = new Core(new FakeDefaultDirectories(), false);
        $core->getConfig()->addConfig(new ArrayAdapter([
            'berlioz' => ['router' => ['rewriteRequestUri' => true]],
        ]));
        $app = new HttpApp($core);

        // The route is defined without the prefix and is matched on the bare path,
        // while the request URI reaching the app is rewritten with the prefix.
        $app->handle(
            new ServerRequest(
                'GET',
                'http://getberlioz.com/controller1/method1',
                serverParams: [
                    'REMOTE_ADDR' => '10.0.0.1',
                    'HTTP_X_FORWARDED_PREFIX' => '/app',
                ],
            )
        );

        // Route matching succeeded on the bare (unprefixed) path.
        $this->assertInstanceOf(Route::class, $app->getRoute());
        $this->assertEquals([ControllerOne::class, 'methodOne'], $app->getRoute()->getContext());

        // The application-wide request now carries the prefixed URI.
        $this->assertEquals('/app/controller1/method1', $app->getRequest()->getUri()->getPath());
    }

    public function testHandle_withForwardedPrefix_fromUntrustedProxy_isNoOp()
    {
        $core = new Core(new FakeDefaultDirectories(), false);
        $core->getConfig()->addConfig(new ArrayAdapter([
            'berlioz' => ['router' => ['rewriteRequestUri' => true]],
        ]));
        $app = new HttpApp($core);

        $app->handle(
            new ServerRequest(
                'GET',
                'http://getberlioz.com/controller1/method1',
                serverParams: [
                    'REMOTE_ADDR' => '203.0.113.7',
                    'HTTP_X_FORWARDED_PREFIX' => '/app',
                ],
            )
        );

        $this->assertInstanceOf(Route::class, $app->getRoute());
        $this->assertEquals('/controller1/method1', $app->getRequest()->getUri()->getPath());
    }

    public static function provideDisabledRequestRewriting(): array
    {
        return [
            'default' => [[]],
            'explicitly disabled' => [['rewriteRequestUri' => false]],
            'prefix disabled' => [['rewriteRequestUri' => true, 'X-Forwarded-Prefix' => false]],
        ];
    }

    #[DataProvider('provideDisabledRequestRewriting')]
    public function testHandle_withForwardedPrefix_rewritingDisabled(array $options): void
    {
        $core = new Core(new FakeDefaultDirectories(), false);
        $core->getConfig()->addConfig(new ArrayAdapter(
            ['berlioz' => ['router' => $options]],
            priority: PHP_INT_MAX,
        ));
        $app = new HttpApp($core);

        $response = $app->handle(new ServerRequest(
            'GET',
            'http://getberlioz.com/controller1/method1?page=2',
            serverParams: [
                'REMOTE_ADDR' => '10.0.0.1',
                'HTTP_X_FORWARDED_PREFIX' => '/app',
            ],
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertInstanceOf(Route::class, $app->getRoute());
        $this->assertSame('/controller1/method1', $app->getRequest()->getUri()->getPath());
        $this->assertSame('page=2', $app->getRequest()->getUri()->getQuery());
        $this->assertNull($app->getRequest()->getAttribute('berlioz.forwarded_prefix'));
    }

    public function testHandle()
    {
        $app = new HttpApp(new Core(new FakeDefaultDirectories(), false));
        $response = $app->handle($serverRequest = new ServerRequest('GET', '/controller2/foo/method1'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('ControllerTwo::methodOne', (string)$response->getBody());
        $this->assertEmpty($serverRequest->getAttributes());
        $this->assertEquals(['attribute1' => 'foo'], $app->getRequest()->getAttributes());
    }

    public static function provideRequestContextRewriting(): array
    {
        return ['disabled' => [false], 'enabled' => [true]];
    }

    public function testHandle_usesRouterSpecificResolverOptions(): void
    {
        $core = new Core(new FakeDefaultDirectories(), false);
        $core->getConfig()->addConfig(new ArrayAdapter([
            'berlioz' => [
                'proxies' => ['trusted' => ['10.0.0.1']],
                'router' => [
                    'rewriteRequestUri' => true,
                    'X-Forwarded-Prefix' => 'X-Custom-Prefix',
                    'trustedProxies' => ['192.0.2.1'],
                ],
            ],
        ], priority: PHP_INT_MAX));
        $app = new HttpApp($core);
        $router = $app->get(Router::class);
        $this->assertSame($router->getForwardedPrefixResolver(), $app->get(ForwardedPrefixResolver::class));

        foreach (['192.0.2.1' => '/custom', '10.0.0.1' => ''] as $peer => $prefix) {
            $response = $app->handle(new ServerRequest(
                'GET',
                'http://getberlioz.com/controller1/method1',
                serverParams: [
                    'REMOTE_ADDR' => $peer,
                    'HTTP_X_CUSTOM_PREFIX' => '/custom',
                    'HTTP_X_FORWARDED_PREFIX' => '/ignored',
                ],
            ));
            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame($prefix . '/controller1/method1', $app->getRequest()->getUri()->getPath());
            $this->assertSame($prefix . '/controller1/method1', $router->generate($app->getRoute()));
        }
    }

    #[DataProvider('provideRequestContextRewriting')]
    public function testHandle_refreshesRouterContext(bool $rewrite): void
    {
        $originalServer = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_PREFIX'] = '/global';

        try {
            $core = new Core(new FakeDefaultDirectories(), false);
            $core->getConfig()->addConfig(new ArrayAdapter(
                ['berlioz' => ['router' => ['rewriteRequestUri' => $rewrite]]],
                priority: PHP_INT_MAX,
            ));
            $app = new HttpApp($core);

            foreach (['/first', '/second', null] as $prefix) {
                $params = null === $prefix ? [] : [
                    'REMOTE_ADDR' => '10.0.0.1',
                    'HTTP_X_FORWARDED_PREFIX' => $prefix,
                ];
                $response = $app->handle(new ServerRequest(
                    'GET',
                    'http://getberlioz.com/controller1/method1',
                    serverParams: $params,
                ));

                $this->assertSame(200, $response->getStatusCode());
                $this->assertSame(
                    ($prefix ?? '') . '/controller1/method1',
                    $app->getRouter()->generate($app->getRoute()),
                );
                $this->assertSame(
                    ($rewrite ? ($prefix ?? '') : '') . '/controller1/method1',
                    $app->getRequest()->getUri()->getPath(),
                );
                $this->assertSame('/global', $_SERVER['HTTP_X_FORWARDED_PREFIX']);
            }
        } finally {
            $_SERVER = $originalServer;
        }
    }

    public function testHandle_withMiddlewaresOrdered()
    {
        AbstractMiddleware::$calls = [];
        $app = new HttpApp(new Core(new FakeDefaultDirectories(), false));
        $app->handle(new ServerRequest('GET', 'https://getberlioz.com'));

        $this->assertSame(
            [
                FooMiddleware::class,
                BarMiddleware::class,
                BazMiddleware::class,
                QuxMiddleware::class,
            ],
            AbstractMiddleware::$calls,
        );
    }

    public function testPrint()
    {
        $app = new HttpApp(new Core(new FakeDefaultDirectories(), false));
        $response = new Response(
            $body = 'FOO BAR',
            200,
            ['MyFirstHeader' => 'Value1', 'MySecondHeader' => ['Value2', 'Value3']],
            'Hummmm OKKK!'
        );

        $this->expectOutputString($body);
        $app->print($response);
    }

    public function testPrint_alreadyPrinted()
    {
        $app = new HttpApp(new Core(new FakeDefaultDirectories(), false));
        $app->setPrinted(true);
        $response = new Response(
            'FOO BAR',
            200,
            ['MyFirstHeader' => 'Value1', 'MySecondHeader' => ['Value2', 'Value3']],
            'Hummmm OKKK!'
        );

        $this->expectOutputString('');
        $app->print($response);
    }

    public function testPrint_streamNotSeekable()
    {
        $app = new HttpApp(new Core(new FakeDefaultDirectories(), false));
        $response = new Response(
            popen('echo FOO BAR', 'r'),
            200,
            ['MyFirstHeader' => 'Value1', 'MySecondHeader' => ['Value2', 'Value3']],
            'Hummmm OKKK!'
        );

        $this->expectOutputString("FOO BAR\n");
        $app->print($response);
    }
}
