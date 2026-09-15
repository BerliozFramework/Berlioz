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

declare(strict_types=1);

namespace Berlioz\Router\Tests;

use Berlioz\Http\Message\Request;
use Berlioz\Http\Message\Uri;
use Berlioz\Router\Exception\NotFoundException;
use Berlioz\Router\Exception\RoutingException;
use Berlioz\Router\Route;
use Berlioz\Router\RouteAttributes;
use Berlioz\Router\Router;
use PHPUnit\Framework\Attributes\DataProvider;

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

    public function testFinalizePath_withInternalPathOverlappingPrefix()
    {
        $router = new Router(['X-Forwarded-Prefix' => true, 'trustedProxies' => ['10.0.0.1']]);

        $serverParams = [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_PREFIX' => '/app',
        ];

        $this->assertEquals('/app/app/articles', $router->finalizePath('/app/articles', $serverParams));
        $this->assertEquals('/app/app', $router->finalizePath('/app', $serverParams));
        $this->assertEquals('/app/application', $router->finalizePath('/application', $serverParams));
        $this->assertEquals('/app/app?page=2', $router->finalizePath('/app?page=2', $serverParams));
    }

    public static function provideForwardedPrefixes(): array
    {
        return [
            'plain' => ['app', '/app'],
            'outer slashes' => ['//app/nested///', '/app/nested'],
            'encoded space' => ['/my%20app', '/my%20app'],
            'encoded UTF-8' => ['/caf%C3%A9', '/caf%C3%A9'],
            'path punctuation' => ['/v1.0/@app;key=value', '/v1.0/@app;key=value'],
            'empty' => ['', null],
            'root' => ['/', null],
            'array' => [['/app'], null],
            'integer' => [42, null],
            'query' => ['/app?x=1', null],
            'fragment' => ['/app#x', null],
            'backslash' => ['/app\\other', null],
            'list' => ['/one,/two', null],
            'space' => ['/my app', null],
            'newline' => ["/app\r\n", null],
            'dot' => ['/app/./nested', null],
            'parent' => ['/app/../nested', null],
            'encoded parent' => ['/app/%2e%2E/nested', null],
            'encoded query' => ['/app%3Fx', null],
            'encoded fragment' => ['/app%23x', null],
            'encoded backslash' => ['/app%5Cx', null],
            'encoded slash' => ['/app%2fx', null],
            'encoded control' => ['/app%00', null],
            'encoded list' => ['/one%2Ctwo', null],
            'double encoding' => ['/app/%252e%252e', null],
            'invalid escape' => ['/app%2Z', null],
            'truncated escape' => ['/app%', null],
            'internal slashes' => ['/app//nested', null],
            'absolute URI' => ['https://example.com/app', null],
        ];
    }

    #[DataProvider('provideForwardedPrefixes')]
    public function testResolveForwardedPrefix_validation(mixed $value, ?string $expected): void
    {
        $router = new Router(['X-Forwarded-Prefix' => true, 'trustedProxies' => ['10.0.0.1']]);
        $params = ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_PREFIX' => $value];

        $this->assertSame($expected, $router->getForwardedPrefixResolver()->resolve($params));
        $this->assertSame(($expected ?? '') . '/articles', $router->finalizePath('/articles', $params));
        $params['REMOTE_ADDR'] = '203.0.113.7';
        $this->assertNull($router->getForwardedPrefixResolver()->resolve($params));
    }

    public function testFinalizePath_requestContextPrecedence(): void
    {
        $_SERVER['HTTP_X_FORWARDED_PREFIX'] = '/global';
        $router = new Router(['X-Forwarded-Prefix' => true, 'trustedProxies' => ['10.0.0.1']]);
        $router->addRoute(new Route('/articles', name: 'articles'));
        $params = ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_PREFIX' => '/request'];

        $this->assertSame('/global/articles', $router->generate('articles'));
        $router->setServerParams($params);
        $this->assertSame('/request/articles', $router->generate('articles'));
        $this->assertSame('/request/assets/main.css', $router->finalizePath('/assets/main.css'));
        $this->assertSame('/explicit/articles', $router->finalizePath('/articles', array_replace(
            $params,
            ['HTTP_X_FORWARDED_PREFIX' => '/explicit'],
        )));
        $this->assertSame('/articles', $router->finalizePath('/articles', []));
        $this->assertSame('/request/articles', $router->generate('articles'));

        $router->setServerParams([]);
        $this->assertSame('/articles', $router->generate('articles'));
        $router->setServerParams(null);
        $this->assertSame('/global/articles', $router->generate('articles'));
    }

    public function testSerialization_excludesRequestContext(): void
    {
        $_SERVER['HTTP_X_FORWARDED_PREFIX'] = '/global';
        $router = new Router(['X-Forwarded-Prefix' => true, 'trustedProxies' => ['10.0.0.1']]);
        $router->addRoute(new Route('/articles', name: 'articles'));
        $router->setServerParams([
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_PREFIX' => '/request',
            'HTTP_AUTHORIZATION' => 'request-only-secret',
        ]);

        $serialized = serialize($router);
        $this->assertStringNotContainsString('request-only-secret', $serialized);
        $this->assertSame('/global/articles', unserialize($serialized)->generate('articles'));
        $this->assertSame('/request/articles', $router->generate('articles'));
        $router->__unserialize($router->__serialize());
        $this->assertSame('/global/articles', $router->generate('articles'));
    }

    public function testSerialization_rebuildsResolverFromEffectiveOptions(): void
    {
        $router = new Router([
            'X-Forwarded-Prefix' => 'X-Custom-Prefix',
            'trustedProxies' => ['192.0.2.1'],
        ]);
        $params = ['REMOTE_ADDR' => '192.0.2.1', 'HTTP_X_CUSTOM_PREFIX' => '/custom'];
        $resolver = $router->getForwardedPrefixResolver();
        $this->assertSame('/custom', $resolver->resolve($params));

        $restored = unserialize(serialize($router));
        $this->assertNotSame($resolver, $restored->getForwardedPrefixResolver());
        $this->assertSame('/custom', $restored->getForwardedPrefixResolver()->resolve($params));
        $this->assertSame('/custom/articles', $restored->finalizePath('/articles', $params));
        $this->assertNull($restored->getForwardedPrefixResolver()->resolve(array_replace(
            $params,
            ['REMOTE_ADDR' => '10.0.0.1'],
        )));
    }

    public function testGenerate_withInternalPathOverlappingPrefix(): void
    {
        $_SERVER['HTTP_X_FORWARDED_PREFIX'] = '/app';
        $router = new Router(['X-Forwarded-Prefix' => true, 'trustedProxies' => ['10.0.0.1']]);
        $router->addRoute(new Route('/app/articles', name: 'articles'));

        $this->assertSame('/app/app/articles', $router->generate('articles'));
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
