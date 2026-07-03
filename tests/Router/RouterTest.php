<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2020 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\Router\Tests;

use Berlioz\Http\Message\Request;
use Berlioz\Http\Message\Uri;
use Berlioz\Router\Exception\NotFoundException;
use Berlioz\Router\Exception\RoutingException;
use Berlioz\Router\Route;
use Berlioz\Router\RouteAttributes;
use Berlioz\Router\Router;

class RouterTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        $_SERVER['HTTP_X_FORWARDED_PREFIX'] = null;
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_FORWARDED_PREFIX'], $_SERVER['HTTP_X_FORWARDED_PREFIX_CUSTOM'], $_SERVER['REMOTE_ADDR']);
    }

    public function testSerialization()
    {
        $router = new Router(options: ['foo' => 'bar']);
        $router->addRoute(new Route('/path'));

        $serialized = serialize($router);
        $unserialized = unserialize($serialized);

        $this->assertEquals($router, $unserialized);
        $this->assertEquals($router->getRoutes(), $unserialized->getRoutes());
    }

    public function testGetRoutes()
    {
        $router = new Router;
        $router->addRoute($route = new Route('/path'));

        $this->assertContains($route, $router->getRoutes());
    }

    public function testGenerate()
    {
        $router = new Router;
        $router->addRoute($route1 = new Route('/path/{attr1}/sub-path', name: 'route1'));
        $router->addRoute(
            new Route(
                '/path/{attr1}/sub-path/{attr2}',
                defaults: ['attr2' => 'default'],
                name: 'route2',
            )
        );

        $this->assertEquals(
            '/path/test/sub-path',
            $router->generate(
                'route1',
                ['attr1' => 'test']
            )
        );
        $this->assertEquals(
            '/path/test/sub-path',
            $router->generate(
                $route1,
                ['attr1' => 'test']
            )
        );
        $this->assertEquals(
            '/path/test/sub-path/test2',
            $router->generate(
                'route2',
                [
                    'attr1' => 'test',
                    'attr2' => 'test2'
                ]
            )
        );
        $this->assertEquals(
            '/path/test/sub-path/default',
            $router->generate(
                'route2',
                ['attr1' => 'test']
            )
        );
        $this->assertEquals(
            '/path/test/sub-path?querystring1=value1&querystring2=value1',
            $router->generate(
                'route1',
                [
                    'attr1' => 'test',
                    'querystring1' => 'value1',
                    'querystring2' => 'value1'
                ]
            )
        );
        $this->assertEquals(
            '/path/test/sub-path/test2',
            $router->generate(
                'route2',
                [
                    'attr1' => 'test',
                    'attr2' => 'test2'
                ]
            )
        );
    }

    public function testGenerate_withForwardedPrefix_enable()
    {
        $_SERVER['HTTP_X_FORWARDED_PREFIX'] = '/super-prefix/';

        $router = new Router(['X-Forwarded-Prefix' => true, 'trustedProxies' => ['10.0.0.1']]);
        $router->addRoute(new Route('/path/{attr1}/sub-path', name: 'route1'));

        $this->assertEquals(
            '/super-prefix/path/test/sub-path',
            $router->generate(
                'route1',
                ['attr1' => 'test']
            )
        );
    }

    public function testGenerate_withForwardedPrefix_disable()
    {
        $_SERVER['HTTP_X_FORWARDED_PREFIX'] = '/super-prefix/';

        $router = new Router(['X-Forwarded-Prefix' => false, 'trustedProxies' => ['10.0.0.1']]);
        $router->addRoute(new Route('/path/{attr1}/sub-path', name: 'route1'));

        $this->assertEquals(
            '/path/test/sub-path',
            $router->generate(
                'route1',
                ['attr1' => 'test']
            )
        );
    }

    public function testGenerate_withForwardedPrefix_custom()
    {
        $_SERVER['HTTP_X_FORWARDED_PREFIX_CUSTOM'] = '/super-prefix/';

        $router = new Router(['X-Forwarded-Prefix' => 'X-Forwarded-Prefix-Custom', 'trustedProxies' => ['10.0.0.1']]);
        $router->addRoute(new Route('/path/{attr1}/sub-path', name: 'route1'));

        $this->assertEquals(
            '/super-prefix/path/test/sub-path',
            $router->generate(
                'route1',
                ['attr1' => 'test']
            )
        );
    }

    public function testGenerate_withForwardedPrefix_andSchemeInParameter()
    {
        $_SERVER['HTTP_X_FORWARDED_PREFIX'] = '/prefix/';

        $router = new Router(['X-Forwarded-Prefix' => true, 'trustedProxies' => ['10.0.0.1']]);
        $router->addRoute(new Route('/redirect/{url}', name: 'redir'));

        $this->assertEquals(
            '/prefix/redirect/http://evil.com',
            $router->generate('redir', ['url' => 'http://evil.com'])
        );
    }

    public function testGenerate_withForwardedPrefix_untrustedProxy_isIgnored()
    {
        $_SERVER['HTTP_X_FORWARDED_PREFIX'] = '/super-prefix/';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';

        $router = new Router(['X-Forwarded-Prefix' => true, 'trustedProxies' => ['10.0.0.1']]);
        $router->addRoute(new Route('/path/{attr1}/sub-path', name: 'route1'));

        // The forwarded header must be ignored when the direct peer is not trusted.
        $this->assertEquals(
            '/path/test/sub-path',
            $router->generate('route1', ['attr1' => 'test'])
        );
    }

    public function testGenerate_withForwardedPrefix_noTrustedProxyConfigured_isIgnored()
    {
        $_SERVER['HTTP_X_FORWARDED_PREFIX'] = '/super-prefix/';

        $router = new Router(['X-Forwarded-Prefix' => true]);
        $router->addRoute(new Route('/path/{attr1}/sub-path', name: 'route1'));

        // Without any trusted proxy configured, the prefix is never applied.
        $this->assertEquals(
            '/path/test/sub-path',
            $router->generate('route1', ['attr1' => 'test'])
        );
    }

    public function testGenerate_withForwardedPrefix_catchAllAlias()
    {
        $_SERVER['HTTP_X_FORWARDED_PREFIX'] = '/super-prefix/';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';

        $router = new Router(['X-Forwarded-Prefix' => true, 'trustedProxies' => ['*']]);
        $router->addRoute(new Route('/path/{attr1}/sub-path', name: 'route1'));

        $this->assertEquals(
            '/super-prefix/path/test/sub-path',
            $router->generate('route1', ['attr1' => 'test'])
        );
    }

    public function testFinalizePath_withServerParamsArgument()
    {
        $router = new Router(['X-Forwarded-Prefix' => true, 'trustedProxies' => ['10.0.0.1']]);

        $this->assertEquals(
            '/app/articles',
            $router->finalizePath('/articles', [
                'REMOTE_ADDR' => '10.0.0.1',
                'HTTP_X_FORWARDED_PREFIX' => '/app',
            ])
        );
    }

    public function testFinalizePath_isIdempotent()
    {
        $router = new Router(['X-Forwarded-Prefix' => true, 'trustedProxies' => ['10.0.0.1']]);

        $serverParams = [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_PREFIX' => '/app',
        ];

        // Already prefixed: must not double the prefix.
        $this->assertEquals('/app/articles', $router->finalizePath('/app/articles', $serverParams));
        $this->assertEquals('/app', $router->finalizePath('/app', $serverParams));
    }

    public function testFinalizePath_withEmptyHeader_isNoOp()
    {
        $router = new Router(['X-Forwarded-Prefix' => true, 'trustedProxies' => ['10.0.0.1']]);

        $this->assertEquals(
            '/articles',
            $router->finalizePath('/articles', [
                'REMOTE_ADDR' => '10.0.0.1',
                'HTTP_X_FORWARDED_PREFIX' => '',
            ])
        );
    }

    public function testFinalizePath_normalizesSlashes()
    {
        $router = new Router(['X-Forwarded-Prefix' => true, 'trustedProxies' => ['10.0.0.1']]);

        foreach (['app', '/app', 'app/', '/app/'] as $prefix) {
            $this->assertEquals(
                '/app/articles',
                $router->finalizePath('/articles', [
                    'REMOTE_ADDR' => '10.0.0.1',
                    'HTTP_X_FORWARDED_PREFIX' => $prefix,
                ]),
                sprintf('Prefix "%s" should normalize to "/app"', $prefix)
            );
        }
    }

    public function testGenerateWithMissingAttributes()
    {
        $router = new Router;
        $router->addRoute(
            new Route(
                '/path/{attr1}/sub-path/{attr2}',
                defaults: ['attr2' => 'default'],
                name: 'route2',
            )
        );

        $this->expectException(RoutingException::class);
        $router->generate('route2', ['attr2' => 'test2']);
    }

    public function testGenerateWithNotFoundRoute()
    {
        $router = new Router;
        $router->addRoute(new Route('/path/{attr1}/sub-path', name: 'route1'));

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Route "route2" does not exists');
        $router->generate('route2', ['attr2' => 'test2']);
    }

    public function testGenerateWithRouteAttributes()
    {
        $router = new Router;
        $router->addRoute(new Route('/path/{user}/{attr}', name: 'route1'));
        $router->addRoute(new Route('/path/{user}/sub-path', name: 'route2'));

        $fakeRouteAttributes1 = new class implements RouteAttributes {
            public function routeAttributes(): array
            {
                return [
                    'user' => 1,
                    'attr' => 'foo',
                ];
            }
        };
        $fakeRouteAttributes2 = new class implements RouteAttributes {
            public function routeAttributes(): array
            {
                return [
                    'user' => 1,
                ];
            }
        };

        $this->assertEquals(
            '/path/1/foo',
            $router->generate('route1', $fakeRouteAttributes1)
        );
        $this->assertEquals(
            '/path/1/sub-path',
            $router->generate('route2', $fakeRouteAttributes2)
        );
        $this->assertEquals(
            '/path/1/bar',
            $router->generate('route1', [$fakeRouteAttributes2, 'attr' => 'bar'])
        );
        $this->assertEquals(
            '/path/1/sub-path?foo%5Battr%5D=bar',
            $router->generate('route2', [$fakeRouteAttributes2, 'foo' => ['attr' => 'bar']])
        );
        $this->assertEquals(
            '/path/1/sub-path?foo%5Battr%5D%5B0%5D=bar',
            $router->generate('route2', [$fakeRouteAttributes2, 'foo' => ['attr' => ['bar']]])
        );
    }

    public function testIsValid()
    {
        $router = new Router;
        $router->addRoute($route1 = new Route('/path/{attr1}/sub-path', name: 'route1'));
        $router->addRoute(
            new Route(
                '/path/{attr1}/sub-path/{attr2}',
                defaults: ['attr2' => 'default'],
                requirements: ['attr1' => '\d+'],
                name: 'route2',
                method: 'post',
            )
        );

        $this->assertTrue(
            $router->isValid($this->getServerRequest('/path/test/sub-path?querystring1=value1&querystring2=value1'))
        );
        $this->assertFalse(
            $router->isValid(
                $this->getServerRequest('/path/test/sub-path/test?querystring1=value1&querystring2=value1')
            )
        );
        $this->assertFalse(
            $router->isValid($this->getServerRequest('/path/123/sub-path/test?querystring1=value1&querystring2=value1'))
        );
        $this->assertTrue(
            $router->isValid(
                $this->getServerRequest(
                    '/path/123/sub-path/test?querystring1=value1&querystring2=value1',
                    Request::HTTP_METHOD_POST
                )
            )
        );
        $this->assertFalse($router->isValid($this->getServerRequest('/unknown-path/test')));
    }

    public function testHandle()
    {
        $router = new Router;
        $router->addRoute($route1 = new Route('/path/{attr1}/sub-path', name: 'route1'));
        $router->addRoute(
            $route2 = new Route(
                '/path/{attr1}/sub-path/{attr2}',
                defaults: ['attr2' => 'default'],
                requirements: ['attr1' => '\d+'],
                name: 'route2'
            )
        );

        $serverRequest = $this->getServerRequest();
        $this->assertEquals($route1, $router->handle($serverRequest));

        $serverRequest = $serverRequest->withUri(
            Uri::createFromString('https://www.phpunit.com/path/test/sub-path/test')
        );
        $this->assertNull($router->handle($serverRequest));
    }

    public function testHandlePriority()
    {
        $serverRequest = $this->getServerRequest();
        $router = new Router;
        $router->addRoute($route1 = new Route('/path/{attr1}/sub-path', name: 'route1'));
        $router->addRoute($route2 = new Route('/path/{attr1}/sub-path', name: 'route2', priority: 100));

        $this->assertEquals($route2, $router->handle($serverRequest));
    }
}
