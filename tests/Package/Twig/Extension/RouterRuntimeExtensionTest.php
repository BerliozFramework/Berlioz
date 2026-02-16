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

namespace Berlioz\Package\Twig\Tests\Extension;

use Berlioz\Package\Twig\Extension\RouterRuntimeExtension;
use Berlioz\Router\Route;
use Berlioz\Router\Router;
use PHPUnit\Framework\TestCase;
use Twig\Error\RuntimeError;

class RouterRuntimeExtensionTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        unset($_SERVER['HTTP_X_FORWARDED_PREFIX']);
        $this->router = new Router();
        $this->router->addRoute(
            new Route('/', name: 'home'),
            new Route('/route/{var}/{var2}', defaults: ['var2' => 'test'], name: 'a-route')
        );
    }

    public function testFunctionPath()
    {
        $extensionRuntime = new RouterRuntimeExtension($this->router);

        $this->assertEquals('/', $extensionRuntime->functionPath('home'));
        $this->assertEquals(
            '/route/foo/test',
            $extensionRuntime->functionPath('a-route', ['var' => 'foo'])
        );
        $this->assertEquals(
            '/route/foo/bar',
            $extensionRuntime->functionPath('a-route', ['var' => 'foo', 'var2' => 'bar'])
        );
    }

    public function testFunctionPath_missingArgument()
    {
        $this->expectException(RuntimeError::class);
        $this->expectExceptionMessage('Missing attributes "var" to generate route "a-route"');

        $extensionRuntime = new RouterRuntimeExtension($this->router);
        $extensionRuntime->functionPath('a-route');
    }

    public function testFunctionPath_notFound()
    {
        $this->expectException(RuntimeError::class);
        $this->expectExceptionMessage('Route "unknown-route" does not exists');

        $extensionRuntime = new RouterRuntimeExtension($this->router);
        $extensionRuntime->functionPath('unknown-route');
    }

    public function testFunctionPathExists()
    {
        $extensionRuntime = new RouterRuntimeExtension($this->router);

        $this->assertTrue($extensionRuntime->functionPathExists('home'));
        $this->assertTrue($extensionRuntime->functionPathExists('a-route', ['var' => 'foo']));
        $this->assertTrue($extensionRuntime->functionPathExists('a-route', ['var' => 'foo', 'var2' => 'bar']));
        $this->assertFalse($extensionRuntime->functionPathExists('a-route'));
        $this->assertFalse($extensionRuntime->functionPathExists('unknown-route'));
    }

    public function testFunctionFinalizePath()
    {
        $extensionRuntime = new RouterRuntimeExtension(new Router(['X-Forwarded-Prefix' => true]));

        $this->assertEquals('/path', $extensionRuntime->functionFinalizePath('/path'));

        $_SERVER['HTTP_X_FORWARDED_PREFIX'] = '/prefix';

        $this->assertEquals('/prefix/path', $extensionRuntime->functionFinalizePath('/path'));
    }
}
