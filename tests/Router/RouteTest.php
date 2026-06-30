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
use Berlioz\Router\Attribute;
use Berlioz\Router\Exception\RoutingException;
use Berlioz\Router\Route;
use Exception;

class RouteTest extends AbstractTestCase
{
    public function testConstructorWithOnlyFirstParameter()
    {
        $route = new Route($path = '/my-path/{foo}/{bar}');

        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals($path, $route->getPath());
    }

    public function testConstructorWithDuplicateAttribute()
    {
        $this->expectException(RoutingException::class);
        $this->expectExceptionMessage('Duplicate attribute name "foo" in route path');

        new Route('/{foo}/{foo}');
    }

    public function testConstructor()
    {
        $route = new Route(
            '/my-path/{foo}/{bar}',
            ['foo' => 'value', 'bar' => false, 'baz' => 'default'],
            ['foo' => '\d+', 'bar' => null],
            'my-route',
            [Request::HTTP_METHOD_GET],
            ['getberlioz.com'],
            100,
            $options = ['option' => true],
            $context = ['controller' => 'TestTestController'],
        );

        $this->assertInstanceOf(Route::class, $route);

        $this->assertInstanceOf(Attribute::class, $route->getAttribute('foo'));
        $this->assertEquals('value', $route->getAttribute('foo')->getDefault());
        $this->assertEquals('\d+', $route->getAttribute('foo')->getRegex());

        $this->assertInstanceOf(Attribute::class, $route->getAttribute('bar'));
        $this->assertEquals(false, $route->getAttribute('bar')->getDefault());
        $this->assertNull($route->getAttribute('bar')->getRegex());

        $this->assertInstanceOf(Attribute::class, $route->getAttribute('baz'));
        $this->assertEquals('default', $route->getAttribute('baz')->getDefault());
        $this->assertNull($route->getAttribute('baz')->getRegex());

        $this->assertEquals('my-route', $route->getName());
        $this->assertEquals([Request::HTTP_METHOD_GET], $route->getMethods());
        $this->assertEquals(['getberlioz.com'], $route->getHosts());
        $this->assertEquals(100, $route->getPriority());

        $this->assertEquals($options, $route->getOptions());
        $this->assertEquals(true, $route->getOption('option'));
        $this->assertNull($route->getOption('unknown'));

        $this->assertEquals($context, $route->getContext());
    }

    public function testConstructor_withoutParameters()
    {
        $route = new Route();

        $this->assertInstanceOf(Route::class, $route);
    }

    public function testSerialization()
    {
        $route = new Route(
            '/my-path/{foo}/{bar}',
            ['foo' => 'value', 'bar' => false, 'baz' => 'default'],
            ['foo' => '\d+', 'bar' => null],
            'my-route',
            [Request::HTTP_METHOD_GET],
            'getberlioz.com',
            100,
            $options = ['option' => true],
            $context = ['controller' => 'TestTestController'],
        );

        $serialized = serialize($route);
        $unserialized = unserialize($serialized);

        $this->assertEquals($route, $unserialized);
    }

    public function testGetName()
    {
        $route = new Route(
            '/my-path/{foo}/{bar}',
            name: 'my-route',
        );
        $this->assertEquals('my-route', $route->getName());
    }

    public function testGetOptions()
    {
        $route = new Route(
            '/my-path/{foo}/{bar}',
            options: $options = ['option' => true],
        );

        $this->assertEquals($options, $route->getOptions());
    }

    public function testGetOption()
    {
        $route = new Route(
            '/my-path/{foo}/{bar}',
            options: ['option' => true],
        );

        $this->assertEquals(true, $route->getOption('option'));
        $this->assertEquals(false, $route->getOption('unknown', false));
        $this->assertNull($route->getOption('unknown'));
    }

    public function testGetContext()
    {
        $route = new Route(
            '/my-path/{foo}/{bar}',
            context: ['controller' => 'TestTestController'],
        );
        $this->assertEquals(
            ['controller' => 'TestTestController'],
            $route->getContext()
        );
    }

    public function testSetContext()
    {
        $route = new Route('/my-path/{foo}/{bar}');
        $context = [
            'controller' => 'Test2Test2Controller',
            'function' => 'MyFunction',
        ];

        $route->setContext($context);
        $this->assertEquals($context, $route->getContext());
    }

    public function testGetMethods()
    {
        $route = new Route(
            '/my-path/{foo}/{bar}',
            method: [
                Request::HTTP_METHOD_POST,
                Request::HTTP_METHOD_GET,
                Request::HTTP_METHOD_PUT,
            ]
        );

        $this->assertEquals(
            [
                Request::HTTP_METHOD_POST,
                Request::HTTP_METHOD_GET,
                Request::HTTP_METHOD_PUT,
            ],
            $route->getMethods()
        );
    }

    public function testGetMethodsWithOneMethod()
    {
        $route = new Route(
            '/my-path/{foo}/{bar}',
            method: Request::HTTP_METHOD_GET
        );

        $this->assertEquals([Request::HTTP_METHOD_GET], $route->getMethods());
    }

    public function testGetMethodsWithPatch()
    {
        $route = new Route('/my-path', method: 'PATCH');

        $this->assertEquals(['PATCH'], $route->getMethods());
    }

    public function testGetMethodsWithNonStandard()
    {
        $route = new Route('/my-path', method: ['PATCH', 'PURGE']);

        $this->assertEquals(['PATCH'], array_values($route->getMethods()));
    }

    public function testGetMethodsDefault()
    {
        $route = new Route('/my-path/{foo}/{bar}');

        $this->assertEquals(
            [
                Request::HTTP_METHOD_GET,
                Request::HTTP_METHOD_HEAD,
                Request::HTTP_METHOD_POST,
                Request::HTTP_METHOD_OPTIONS,
                Request::HTTP_METHOD_CONNECT,
                Request::HTTP_METHOD_TRACE,
                Request::HTTP_METHOD_PUT,
                Request::HTTP_METHOD_PATCH,
                Request::HTTP_METHOD_DELETE,
            ],
            $route->getMethods()
        );
    }

    public function testGetHostsInheritedFromParent()
    {
        $parentRoute = new Route('/api', host: 'api.example.com');
        $parentRoute->addRoute($childRoute = new Route('/users'));

        $this->assertEquals(['api.example.com'], $childRoute->getHosts());

        // Child route should reject requests from other hosts
        $this->assertFalse($childRoute->test($this->getServerRequest('https://evil.com/api/users')));
        $this->assertTrue($childRoute->test($this->getServerRequest('https://api.example.com/api/users')));
    }

    public function testGetHostsChildOverridesParent()
    {
        $parentRoute = new Route('/api', host: 'api.example.com');
        $parentRoute->addRoute($childRoute = new Route('/users', host: 'other.example.com'));

        $this->assertEquals(['other.example.com'], $childRoute->getHosts());
    }

    public function testGetRoute()
    {
        $route = new Route('/my-path/{foo}/{bar}');

        $this->assertEquals('/my-path/{foo}/{bar}', $route->getPath());
    }

    public function testTestRoute()
    {
        $route = new Route('/my-path/{foo}/{bar}');

        $this->assertTrue($route->test($this->getServerRequest('/my-path/1value1/value2')));
        $this->assertFalse($route->test($this->getServerRequest('/my-path/1va/lue1/value2')));
    }

    public function testTestRouteWithRequirements()
    {
        $route = new Route(
            '/my-path/{foo}/{bar}',
            requirements: [
                'foo' => '\d+',
                'bar' => '.+',
            ],
        );
        $this->assertTrue($route->test($this->getServerRequest('/my-path/123/value2')));
        $this->assertFalse($route->test($this->getServerRequest('/my-path/12-3/value2')));
        $this->assertTrue($route->test($this->getServerRequest('/my-path/123/valu/e2')));

        $route = new Route(
            '/my-path/{foo}/{bar}',
            requirements: [
                'foo' => '\d+',
                'bar' => '.*',
            ],
        );
        $this->assertTrue($route->test($this->getServerRequest('/my-path/123/')));
        $this->assertTrue($route->test($this->getServerRequest('/my-path/123/value2')));
    }

    public function testTestRouteWithTildeInRequirement()
    {
        $route = new Route(
            '/test/{foo}',
            requirements: ['foo' => 'a~b'],
        );

        $this->assertTrue($route->test($this->getServerRequest('/test/a~b')));
        $this->assertFalse($route->test($this->getServerRequest('/test/abc')));
    }

    public function testTestRouteWithInlineRegexZero()
    {
        $route = new Route('/test/{foo:0}');

        $this->assertTrue($route->test($this->getServerRequest('/test/0')));
        $this->assertFalse($route->test($this->getServerRequest('/test/1')));
    }

    public function testTestRouteWithRequirementsInPath()
    {
        $route = new Route('/my-path/{foo::int}/{bar}');
        $route2 = new Route('/my-path/{foo:\d+}/{bar}');

        $this->assertTrue($route->test($this->getServerRequest('/my-path/123/value2')));
        $this->assertTrue($route2->test($this->getServerRequest('/my-path/123/value2')));
        $this->assertFalse($route->test($this->getServerRequest('/my-path/12-3/value2')));
        $this->assertFalse($route2->test($this->getServerRequest('/my-path/12-3/value2')));
    }

    public function testTestRouteWithInlineRegexContainingBraces()
    {
        $route = new Route('/{param:([0-9]{2}\.?[0-9]{2}[a-zA-Z]{1})}');

        $this->assertEquals('([0-9]{2}\.?[0-9]{2}[a-zA-Z]{1})', $route->getAttribute('param')->getRegex());
        $this->assertTrue($route->test($this->getServerRequest('/12.34A')));
        $this->assertTrue($route->test($this->getServerRequest('/1234a')));
        $this->assertFalse($route->test($this->getServerRequest('/12.34')));
        $this->assertFalse($route->test($this->getServerRequest('/abcde')));
    }

    public function testTestRouteWithInlineRegexBracesMatchesRequirements()
    {
        $inline = new Route('/{param:([0-9]{2}\.?[0-9]{2}[a-zA-Z]{1})}');
        $withRequirements = new Route(
            '/{param}',
            requirements: ['param' => '([0-9]{2}\.?[0-9]{2}[a-zA-Z]{1})'],
        );

        foreach (['/12.34A', '/1234a', '/12.34', '/abcde'] as $path) {
            $this->assertSame(
                $withRequirements->test($this->getServerRequest($path)),
                $inline->test($this->getServerRequest($path)),
                sprintf('Mismatch between inline and requirements form for path "%s"', $path),
            );
        }
    }

    public function testTestRouteWithInlineRegexBracesAndAttributes()
    {
        $route = new Route('/{param:[0-9]{2}\.[0-9]{2}}');

        $attributes = [];
        $this->assertTrue($route->test($this->getServerRequest('/12.34'), $attributes));
        $this->assertEquals(['param' => '12.34'], $attributes);
        $this->assertFalse($route->test($this->getServerRequest('/1234')));
    }

    public function testTestRouteWithInlineRegexBracedQuantifierRange()
    {
        $route = new Route('/{page:[0-9]{1,3}}');

        $this->assertTrue($route->test($this->getServerRequest('/7')));
        $this->assertTrue($route->test($this->getServerRequest('/123')));
        $this->assertFalse($route->test($this->getServerRequest('/1234')));
    }

    public function testTestRouteWithFloatType()
    {
        $route = new Route('/price/{amount::float}');

        $this->assertTrue($route->test($this->getServerRequest('/price/42.5')));
        $this->assertTrue($route->test($this->getServerRequest('/price/42')));
        $this->assertFalse($route->test($this->getServerRequest('/price/abc')));
    }

    public function testTestRouteWithRequirementsInPath_deprecated()
    {
        set_error_handler(
            function (int $errno, string $errstr): void {
                throw new Exception($errstr, $errno);
            },
            E_USER_DEPRECATED
        );

        try {
            $this->expectExceptionCode(E_USER_DEPRECATED);

            $route = new Route('/my-path/{foo::uuid}');
            $this->assertTrue($route->test($this->getServerRequest('/my-path/8bd71855-5e84-4a0e-9595-98a5f180840d')));
        } finally {
            restore_error_handler();
        }
    }

    public function testTestWithAttributes()
    {
        $route = new Route('/my-path/{foo}/{bar}');
        $attributes = [];
        $route->test($this->getServerRequest('/my-path/value1/value2'), $attributes);

        $this->assertEquals(
            [
                'foo' => 'value1',
                'bar' => 'value2',
            ],
            $attributes
        );
    }

    public function testTestWithOptionalPart()
    {
        $route = new Route('/my-path/{foo}[/{bar}]');

        $this->assertTrue($route->test($this->getServerRequest('/my-path/value1')));
        $this->assertTrue($route->test($this->getServerRequest('/my-path/value1/value2')));
    }

    public function testTestWithOptionalPartWith2Attributes()
    {
        $route = new Route('/my-path/{foo}[/{bar}/{baz}]');

        $attributes = [];
        $this->assertTrue($route->test($this->getServerRequest('/my-path/value1'), $attributes));
        $this->assertEquals(
            [
                'foo' => 'value1',
            ],
            $attributes
        );

        $this->assertFalse($route->test($this->getServerRequest('/my-path/value1/value2')));

        $attributes = [];
        $this->assertTrue($route->test($this->getServerRequest('/my-path/value1/value2/value3'), $attributes));
        $this->assertEquals(
            [
                'foo' => 'value1',
                'bar' => 'value2',
                'baz' => 'value3',
            ],
            $attributes
        );
    }

    public function testTestWithNestedOptionalPart()
    {
        $route = new Route('/my-path/{foo}[[/{bar}]/sub-path/{baz}]');

        $attributes = [];
        $this->assertTrue($route->test($this->getServerRequest('/my-path/value1'), $attributes));
        $this->assertEquals(
            [
                'foo' => 'value1',
            ],
            $attributes
        );

        $attributes = [];
        $this->assertTrue($route->test($this->getServerRequest('/my-path/value1/sub-path/value3'), $attributes));
        $this->assertEquals(
            [
                'foo' => 'value1',
                'baz' => 'value3',
            ],
            $attributes
        );

        $attributes = [];
        $this->assertTrue($route->test($this->getServerRequest('/my-path/value1/value2/sub-path/value3'), $attributes));
        $this->assertEquals(
            [
                'foo' => 'value1',
                'bar' => 'value2',
                'baz' => 'value3',
            ],
            $attributes
        );
    }

    public function testTestForParentRoute()
    {
        $parentRoute = new Route(
            '/path/{foo}',
            requirements: ['bar' => '\d+']
        );
        $parentRoute->addRoute($route = new Route('/sub-path/{bar}', defaults: ['foo' => 'value']));

        $this->assertFalse($parentRoute->test($this->getServerRequest('/path/value/sub-path/123')));
        $this->assertTrue($route->test($this->getServerRequest('/path/value/sub-path/123')));
    }

    public function testGenerate()
    {
        $route = new Route('/my-path/{foo}/{bar}');
        $this->assertEquals(
            '/my-path/value1/value2',
            $route->generate(['foo' => 'value1', 'bar' => 'value2'])
        );
        $this->assertTrue(
            $route->test($this->getServerRequest($route->generate(['foo' => 'value1', 'bar' => 'value2'])))
        );

        $this->assertEquals(
            '/my-path/foo/bar/value2',
            $route->generate(['foo' => 'foo/bar', 'bar' => 'value2'])
        );
        $this->assertFalse(
            $route->test($this->getServerRequest($route->generate(['foo' => 'foo/bar', 'bar' => 'value2'])))
        );

        $this->assertEquals(
            '/my-path/foo%2Fbar/value2',
            $route->generate(['foo' => urlencode('foo/bar'), 'bar' => 'value2'])
        );
        $this->assertTrue(
            $route->test($this->getServerRequest($route->generate(['foo' => urlencode('foo/bar'), 'bar' => 'value2'])))
        );
    }

    public function testGenerateWithDefaultAttribute()
    {
        $route = new Route('/my-path/{foo}/{bar}', defaults: ['foo' => 'value1']);
        $this->assertEquals(
            '/my-path/value1/value2',
            $route->generate(['bar' => 'value2'])
        );
    }

    public function testGenerateWithMissingAttribute()
    {
        $this->expectException(RoutingException::class);

        $route = new Route('/my-path/{foo}/{bar}', defaults: ['foo' => 'value1']);
        $this->assertEquals(
            '/my-path/value1/value2',
            $route->generate()
        );
    }

    public function testGenerateWithMultidimensionalParameters()
    {
        $route = new Route(
            '/my-path/{foo}/{bar}',
            defaults: ['foo' => 'bar'],
            name: 'my-route',
            method: 'get'
        );
        $parameters = [
            'baz' => ['bar', 'baz', '', null, 0],
            'qux' => '',
            'quxx' => null,
            'foo' => 'value1',
            'bar' => 'value2'
        ];

        $this->assertEquals(
            '/my-path/value1/value2?baz%5B0%5D=bar&baz%5B1%5D=baz&baz%5B2%5D=&baz%5B4%5D=0&qux=',
            $route->generate($parameters)
        );
        $this->assertTrue($route->test($this->getServerRequest($route->generate($parameters))));
    }

    public function testGenerateWithParentRoute()
    {
        $parentRoute = new Route(
            '/path/{foo}',
            requirements: ['bar' => '\d+']
        );
        $parentRoute->addRoute($route = new Route('/sub-path/{bar}', defaults: ['foo' => 'value']));

        $this->assertEquals('/path/value/sub-path/123', $route->generate(['bar' => '123']));
    }

    public function testGenerateWithParentRouteAndEmptyPath()
    {
        $parentRoute = new Route(requirements: ['bar' => '\d+']);
        $parentRoute->addRoute($route = new Route('/sub-path/{bar}'));

        $this->assertEquals('/sub-path/123', $route->generate(['bar' => '123']));
    }

    public function testGenerateWithOptionalPart()
    {
        $route = new Route('/my-path/{foo}[/{bar}]');

        $this->assertEquals('/my-path/value1', $route->generate(['foo' => 'value1']));
        $this->assertEquals('/my-path/value1', $route->generate(['foo' => 'value1', 'bar' => null]));
        $this->assertEquals('/my-path/value1/value2', $route->generate(['foo' => 'value1', 'bar' => 'value2']));
    }

    public function testGenerateWithOptionalPartWith2Attributes()
    {
        $route = new Route('/my-path/{foo}[/{bar}/{baz}]');

        $this->assertEquals('/my-path/value1', $route->generate(['foo' => 'value1']));
        $this->assertEquals('/my-path/value1', $route->generate(['foo' => 'value1', 'bar' => 'value2']));
        $this->assertEquals(
            '/my-path/value1/value2/value3',
            $route->generate(['foo' => 'value1', 'bar' => 'value2', 'baz' => 'value3'])
        );
    }

    public function testGenerateWithNestedOptionalPart()
    {
        $route = new Route('/my-path/{foo}[[/{bar}]/sub-path/{baz}]');

        $this->assertEquals('/my-path/value1', $route->generate(['foo' => 'value1']));
        $this->assertEquals(
            '/my-path/value1/sub-path/value3',
            $route->generate(['foo' => 'value1', 'baz' => 'value3'])
        );
        $this->assertEquals(
            '/my-path/value1/value2/sub-path/value3',
            $route->generate(['foo' => 'value1', 'bar' => 'value2', 'baz' => 'value3'])
        );
    }

    public function testGenerateWithNestedOptionalPartAndDefaultValue()
    {
        $route = new Route('/my-path/{foo}[[/{bar}]/sub-path/{baz}]', defaults: ['baz' => 'default']);

        $this->assertEquals('/my-path/value1/sub-path/default', $route->generate(['foo' => 'value1']));
    }

    public function testGetRoutes()
    {
        $parentRoute = new Route(
            '/path/{foo}',
            requirements: ['bar' => '\d+']
        );
        $parentRoute->addRoute(new Route('/sub-path/{bar}', defaults: ['foo' => 'value']));
        $parentRoute2 = new Route('/path2/{foo}');
        $parentRoute2->addRoute(new Route('/sub-path2/{bar}', defaults: ['foo' => 'value']));
        $parentRoute->addRoute($parentRoute2);

        $routes = iterator_to_array($parentRoute->getRoutes(), false);
        $this->assertCount(2, $routes);
    }

    public function testGetRouteByNameForGroup()
    {
        $router = new Route('/root');
        $group = new Route('/api', name: 'api-group');
        $group->addRoute(new Route('/users', name: 'users'));
        $router->addRoute($group);

        $this->assertSame($group, $router->getRoute('api-group'));
        $this->assertNotNull($router->getRoute('users'));
    }

    public function testCountWithGroups()
    {
        $parentRoute = new Route('/path/{foo}');
        $parentRoute->addRoute(new Route('/child1'));

        $group = new Route('/group');
        $group->addRoute(new Route('/child2'));
        $group->addRoute(new Route('/child3'));
        $parentRoute->addRoute($group);

        // 3 leaf routes: child1 + child2 + child3
        $this->assertCount(3, $parentRoute);
        $this->assertSame(iterator_count($parentRoute->getRoutes()), count($parentRoute));
    }
}
