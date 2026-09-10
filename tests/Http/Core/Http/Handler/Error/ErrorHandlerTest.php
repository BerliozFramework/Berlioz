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

namespace Berlioz\Http\Core\Tests\Http\Handler\Error;

use Berlioz\Config\Adapter\ArrayAdapter;
use Berlioz\Config\Adapter\JsonAdapter;
use Berlioz\Core\Core;
use Berlioz\Core\Tests\RestoresErrorHandler;
use Berlioz\Http\Core\App\HttpApp;
use Berlioz\Http\Core\Container\ServiceProvider;
use Berlioz\Http\Core\Exception\Http\ForbiddenHttpException;
use Berlioz\Http\Core\Exception\Http\InternalServerErrorHttpException;
use Berlioz\Http\Core\Exception\Http\NotFoundHttpException;
use Berlioz\Http\Core\Http\Handler\Error\ErrorHandler;
use Berlioz\Http\Core\TestProject\FakeDefaultDirectories;
use Berlioz\Http\Message\ServerRequest;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Throwable;

class ErrorHandlerTest extends TestCase
{
    use RestoresErrorHandler;

    private string $errorLog;
    private string $previousErrorLog;

    protected function setUp(): void
    {
        $this->errorLog = tempnam(sys_get_temp_dir(), 'berlioz-errors-');
        $this->previousErrorLog = ini_set('error_log', $this->errorLog);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->previousErrorLog);
        unlink($this->errorLog);
    }

    public static function provideLoggedExceptions(): iterable
    {
        yield 'production exception' => [false, new RuntimeException('Private application failure'), true];
        yield 'debug exception' => [true, new RuntimeException('Private application failure'), true];
        yield 'HTTP 500' => [false, new InternalServerErrorHttpException(), true];
        yield 'HTTP 404' => [false, new NotFoundHttpException(), false];
        yield 'HTTP 403' => [false, new ForbiddenHttpException(), false];
        yield 'chained exception' => [
            false,
            new RuntimeException('Outer failure', previous: new RuntimeException('Original failure')),
            true,
        ];
    }

    #[DataProvider('provideLoggedExceptions')]
    public function testHandle_logsExceptions(bool $debug, Throwable $exception, bool $logged): void
    {
        $app = $this->getApp();
        $app->getDebug()->setEnabled($debug);
        $phpErrors = count($app->getDebug()->getSnapshot()->getPhpErrors());
        $response = (new ErrorHandler($app))->handle(new ServerRequest('GET', '/'), $exception);
        $log = file_get_contents($this->errorLog);

        $snapshot = $app->getDebug()->getSnapshot();
        $this->assertSame($debug ? [(string)$exception] : [], $snapshot->getExceptions());
        $this->assertCount($phpErrors, $snapshot->getPhpErrors());

        if ($logged) {
            $this->assertSame(1, substr_count($log, (string)$exception));
        } else {
            $this->assertSame('', $log);
        }

        if (!$debug) {
            $this->assertStringNotContainsString($exception->getTraceAsString(), (string)$response->getBody());
            $this->assertStringNotContainsString('Private application failure', (string)$response->getBody());
            $this->assertStringNotContainsString('Original failure', (string)$response->getBody());
        }
    }

    public function testHandle_logsErrorHandlerFailures(): void
    {
        $core = new Core(new FakeDefaultDirectories(), cache: false);
        $app = $this->getMockBuilder(HttpApp::class)
            ->setConstructorArgs([$core])
            ->onlyMethods(['call', 'getConfigKey'])
            ->getMock();
        $app->method('getConfigKey')->willReturn(['default' => FakeErrorHandler::class]);
        $customFailure = new NotFoundHttpException('Custom handler failure');
        $renderFailure = new RuntimeException('Default renderer failure');
        $app->expects($this->exactly(2))->method('call')->willReturnCallback(
            static function (string $class) use ($customFailure, $renderFailure): never {
                throw $class === FakeErrorHandler::class ? $customFailure : $renderFailure;
            }
        );
        $original = new RuntimeException('Original application failure');
        $response = (new ErrorHandler($app))->handle(new ServerRequest('GET', '/'), $original);
        $log = file_get_contents($this->errorLog);

        foreach ([$original, $customFailure, $renderFailure] as $exception) {
            $this->assertSame(1, substr_count($log, (string)$exception));
            $this->assertStringNotContainsString($exception->getMessage(), (string)$response->getBody());
        }
        $this->assertSame(500, $response->getStatusCode());
    }

    private function getApp(?Core $core = null): HttpApp
    {
        $core ??= new Core(new FakeDefaultDirectories(), cache: false);
        $core->getContainer()->addProvider(new ServiceProvider($core));
        $app = new HttpApp($core);
        $app->getCore()->getConfig()->addConfig(
            new JsonAdapter(__DIR__ . '/../../../../../../src/Http/Core/resources/config.default.json', true),
            new ArrayAdapter(
                [
                    'berlioz' => [
                        'directories' => ['templates' => __DIR__ . '/../../../tests_env/resources/templates']
                    ],
                    'twig' => [
                        'paths' => ['Berlioz-HttpCore' => __DIR__ . '/../../../../../../src/Http/Core/resources']
                    ]
                ],
                1
            )
        );

        return $app;
    }

    public function testHandle()
    {
        $app = $this->getApp();
        $app->getCore()->getConfig()->addConfig(
            new ArrayAdapter(
                [
                    'berlioz' => [
                        'http' => [
                            'errors' => [
                                'default' => FakeErrorHandler::class
                            ]
                        ]
                    ]
                ]
            )
        );
        $handler = new ErrorHandler($app);

        $this->assertFalse(FakeErrorHandler::$handled);

        $handler->handle(new ServerRequest('GET', '/'));

        $this->assertTrue(FakeErrorHandler::$handled);
    }

    public function testHandle_onSpecificErrorCore()
    {
        $app = $this->getApp();
        $app->getCore()->getConfig()->addConfig(
            new ArrayAdapter(
                [
                    'berlioz' => [
                        'http' => [
                            'errors' => [
                                '404' => FakeErrorHandler::class,
                                '403' => FakeErrorHandler2::class,
                            ]
                        ]
                    ]
                ]
            )
        );
        $handler = new ErrorHandler($app);

        FakeErrorHandler::$handled = FakeErrorHandler2::$handled = false;
        $this->assertFalse(FakeErrorHandler::$handled);
        $this->assertFalse(FakeErrorHandler2::$handled);

        $handler->handle(new ServerRequest('GET', '/'), new InternalServerErrorHttpException());

        $this->assertFalse(FakeErrorHandler::$handled);
        $this->assertFalse(FakeErrorHandler2::$handled);
        FakeErrorHandler::$handled = FakeErrorHandler2::$handled = false;
        $this->assertFalse(FakeErrorHandler::$handled);
        $this->assertFalse(FakeErrorHandler2::$handled);

        $handler->handle(new ServerRequest('GET', '/'), new NotFoundHttpException());

        $this->assertTrue(FakeErrorHandler::$handled);
        $this->assertFalse(FakeErrorHandler2::$handled);
        FakeErrorHandler::$handled = FakeErrorHandler2::$handled = false;
        $this->assertFalse(FakeErrorHandler::$handled);
        $this->assertFalse(FakeErrorHandler2::$handled);

        $handler->handle(new ServerRequest('GET', '/'), new ForbiddenHttpException());

        $this->assertFalse(FakeErrorHandler::$handled);
        $this->assertTrue(FakeErrorHandler2::$handled);
    }

    public function testHandle_default()
    {
        $app = $this->getApp();
        $handler = new ErrorHandler($app);
        $response = $handler->handle(new ServerRequest('GET', '/'));

        $this->assertStringContainsString('500 error', (string)$response->getBody());
        $this->assertStringContainsString('Looks like we\'re having some server issues', (string)$response->getBody());
        $this->assertStringNotContainsString('<pre>', (string)$response->getBody());
    }

    public function testHandle_defaultDebugEnabled()
    {
        $core = new Core(new FakeDefaultDirectories(), cache: false);
        $core->getDebug()->setEnabled(true);
        $app = $this->getApp($core);
        $handler = new ErrorHandler($app);
        $response = $handler->handle(new ServerRequest('GET', '/'));

        $this->assertStringContainsString('500 error', (string)$response->getBody());
        $this->assertStringContainsString('Looks like we\'re having some server issues', (string)$response->getBody());
        $this->assertStringContainsString('<pre class="debug">', (string)$response->getBody());
    }

    public function testHandle_fallback()
    {
        $core = new Core(new FakeDefaultDirectories(), cache: false);
        $handler = new ErrorHandler($this->getApp($core));
        $response = $handler->handle(new ServerRequest('GET', '/'));

        $this->assertStringContainsString('Internal Server Error', (string)$response->getBody());
        $this->assertStringNotContainsString('<pre>', (string)$response->getBody());
    }

    public function testHandle_fallbackDebugEnabled()
    {
        $core = new Core(new FakeDefaultDirectories(), cache: false);
        $handler = new ErrorHandler($this->getApp($core));
        $core->getDebug()->setEnabled(true);
        $response = $handler->handle(new ServerRequest('GET', '/'));

        $this->assertStringContainsString('Internal Server Error', (string)$response->getBody());
        $this->assertStringContainsString('<pre', (string)$response->getBody());
    }
}
