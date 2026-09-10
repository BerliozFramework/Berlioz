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
use Berlioz\Http\Core\Debug\RouterSection;
use Berlioz\Http\Core\TestProject\Controller\ControllerOne;
use Berlioz\Http\Core\TestProject\FakeDefaultDirectories;
use Berlioz\Http\Core\TestProject\Http\Middleware\AbstractMiddleware;
use Berlioz\Http\Core\TestProject\Http\Middleware\BarMiddleware;
use Berlioz\Http\Core\TestProject\Http\Middleware\BazMiddleware;
use Berlioz\Http\Core\TestProject\Http\Middleware\FooMiddleware;
use Berlioz\Http\Core\TestProject\Http\Middleware\QuxMiddleware;
use Berlioz\Http\Message\Response;
use Berlioz\Http\Message\ServerRequest;
use Berlioz\Router\Route;
use Berlioz\Router\RouterInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use RuntimeException;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

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

    public function testHandle()
    {
        $app = new HttpApp(new Core(new FakeDefaultDirectories(), false));
        $response = $app->handle($serverRequest = new ServerRequest('GET', '/controller2/foo/method1'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('ControllerTwo::methodOne', (string)$response->getBody());
        $this->assertEmpty($serverRequest->getAttributes());
        $this->assertEquals(['attribute1' => 'foo'], $app->getRequest()->getAttributes());
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

    public function testResponseInfo_afterMiddlewareWithoutAccessingBody(): void
    {
        $core = new Core(new FakeDefaultDirectories(), false);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(202);
        $response->method('getReasonPhrase')->willReturn('Queued');
        $response->method('getProtocolVersion')->willReturn('2');
        $response->method('getHeaders')->willReturn(['Set-Cookie' => ['a=1', 'b=2']]);
        $response->expects($this->never())->method('getBody');
        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->method('process')->willReturn($response);
        $core->getConfig()->addConfig(new ArrayAdapter([
            'berlioz' => ['http' => ['middlewares' => [-1000 => $middleware]]],
        ]));
        $app = new HttpApp($core);

        $this->assertNull($app->getResponseInfo());
        $this->assertSame($response, $app->handle(new ServerRequest('GET', '/unknown')));
        $this->assertSame([
            'statusCode' => 202,
            'reasonPhrase' => 'Queued',
            'protocolVersion' => '2',
            'headers' => ['Set-Cookie' => ['a=1', 'b=2']],
        ], $app->getResponseInfo());
    }

    public function testResponseInfo_errorResponse(): void
    {
        $app = new HttpApp(new Core(new FakeDefaultDirectories(), false));
        $response = $app->handle(new ServerRequest('GET', '/unknown'));

        $this->assertSame(404, $app->getResponseInfo()['statusCode']);
        $this->assertSame($response->getReasonPhrase(), $app->getResponseInfo()['reasonPhrase']);
    }

    public function testResponseInfo_resetBeforeRoutingFailure(): void
    {
        $app = $this->getMockBuilder(HttpApp::class)
            ->setConstructorArgs([new Core(new FakeDefaultDirectories(), false)])
            ->onlyMethods(['findRoute'])
            ->getMock();
        $app->method('findRoute')->willThrowException(new RuntimeException('Routing failed'));
        $this->expectOutputString('');
        $app->print(new Response('', 201));
        $this->assertSame(201, $app->getResponseInfo()['statusCode']);

        try {
            $app->handle(new ServerRequest('GET', '/'));
            $this->fail('Expected routing failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('Routing failed', $exception->getMessage());
        }
        $this->assertNull($app->getResponseInfo());
    }

    public function testResponseInfo_printAndSnapshot(): void
    {
        $app = new HttpApp(new Core(new FakeDefaultDirectories(), false));
        $app->getDebug()->setEnabled(true);
        $response = $app->handle(new ServerRequest('GET', '/controller1/method1'));
        $response = $response->withStatus(201, 'Custom <Created>')
            ->withHeader('Set-Cookie', ['a=1', 'b=2'])
            ->withHeader('Content-Type', 'application/json');
        $body = (string)$response->getBody();
        $this->expectOutputString($body);
        $app->print($response);

        $section = new RouterSection($app);
        $section->snap($app->getDebug());
        $section = unserialize(serialize($section));
        $this->assertSame($app->getResponseInfo(), $section->getResponseInfo());
        $this->assertSame(201, $section->getResponseInfo()['statusCode']);
        $this->assertSame(
            [$app->getDebug()->getUniqid()],
            $section->getResponseInfo()['headers']['X-Berlioz-Debug'],
        );

        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__ . '/../../../../src/Http/Core/resources', 'Berlioz-HttpCore');
        $twig = new Environment($loader, ['strict_variables' => true]);
        $template = $twig->load($section->getTemplateName());
        $html = $template->renderBlock('main', ['section' => $section]);
        $this->assertStringContainsString('HTTP/1.1 201 Custom &lt;Created&gt;', $html);
        $this->assertStringContainsString("Set-Cookie: a=1\nSet-Cookie: b=2\n", $html);
        $this->assertStringContainsString('Content-Type: application/json', $html);
        $this->assertStringContainsString('201 Custom &lt;Created&gt;', $template->renderBlock('widget', [
            'section' => $section,
        ]));

        $section->__unserialize([]);
        $this->assertNull($section->getResponseInfo());
        $this->assertStringContainsString(
            'No response captured.',
            $template->renderBlock('main', ['section' => $section]),
        );
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
