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

namespace Berlioz\Package\QueueManager\Tests\Http;

use Berlioz\Config\Adapter\ArrayAdapter;
use Berlioz\Config\Config;
use Berlioz\Http\Core\App\HttpApp;
use Berlioz\Http\Message\Response;
use Berlioz\Http\Message\ServerRequest;
use Berlioz\Package\QueueManager\Http\QueueMetricsMiddleware;
use Berlioz\QueueManager\Queue\MemoryQueue;
use Berlioz\QueueManager\Queue\QueueInterface;
use Berlioz\QueueManager\QueueManager;
use Berlioz\Router\RouteInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

class QueueMetricsMiddlewareTest extends TestCase
{
    private function queueManager(): QueueManager
    {
        return new QueueManager(new MemoryQueue('default'));
    }

    private function middleware(array $config, ?RouteInterface $route = null): QueueMetricsMiddleware
    {
        $app = $this->createMock(HttpApp::class);
        $app->method('getConfig')->willReturn(new Config([new ArrayAdapter($config)]));
        $app->method('getRoute')->willReturn($route);

        return new QueueMetricsMiddleware($app, $this->queueManager());
    }

    private function handler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public bool $handled = false;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->handled = true;

                return new Response(null, Response::HTTP_STATUS_NOT_FOUND);
            }
        };
    }

    private function request(
        string $path = '/metrics/queues',
        array $headers = [],
        array $server = [],
        string $method = 'GET',
    ): ServerRequest {
        return new ServerRequest($method, $path, $headers, [], $server);
    }

    private function enabledConfig(array $metrics = []): array
    {
        return ['berlioz' => ['queues' => ['metrics' => array_merge(['enable' => true], $metrics)]]];
    }

    public function testProcess_passThroughWhenPathDoesNotMatch()
    {
        $middleware = $this->middleware($this->enabledConfig());
        $response = $middleware->process($this->request('/foo'), $handler = $this->handler());

        $this->assertTrue($handler->handled);
        $this->assertSame(Response::HTTP_STATUS_NOT_FOUND, $response->getStatusCode());
    }

    public function testProcess_passThroughWhenRouteExists()
    {
        $route = $this->createMock(RouteInterface::class);
        $middleware = $this->middleware($this->enabledConfig(), $route);
        $middleware->process($this->request(), $handler = $this->handler());

        $this->assertTrue($handler->handled);
    }

    public function testProcess_passThroughWhenDisabled()
    {
        $middleware = $this->middleware(['berlioz' => ['queues' => ['metrics' => ['enable' => false]]]]);
        $middleware->process($this->request(), $handler = $this->handler());

        $this->assertTrue($handler->handled);
    }

    public function testProcess_servesPrometheusWhenEnabledWithoutRestriction()
    {
        $middleware = $this->middleware($this->enabledConfig());
        $response = $middleware->process($this->request(), $handler = $this->handler());

        $this->assertFalse($handler->handled);
        $this->assertSame(Response::HTTP_STATUS_OK, $response->getStatusCode());
        $this->assertStringContainsString('text/plain', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('job_queue_length{queue_name="default"} 0', (string)$response->getBody());
    }

    public function testProcess_servesJsonWhenFormatIsJson()
    {
        $middleware = $this->middleware($this->enabledConfig(['format' => 'json']));
        $response = $middleware->process($this->request(), $this->handler());

        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertJson((string)$response->getBody());
    }

    public function testProcess_passThroughWhenMethodIsNotGet()
    {
        $middleware = $this->middleware($this->enabledConfig());
        $middleware->process(
            $this->request(method: 'POST'),
            $handler = $this->handler(),
        );

        $this->assertTrue($handler->handled);
    }

    public function testProcess_servesOnHeadMethodWithEmptyBodyButSameContentType()
    {
        $middleware = $this->middleware($this->enabledConfig());

        $getResponse = $middleware->process($this->request(), $this->handler());
        $headResponse = $middleware->process($this->request(method: 'HEAD'), $handler = $this->handler());

        $this->assertFalse($handler->handled);
        $this->assertSame(Response::HTTP_STATUS_OK, $headResponse->getStatusCode());

        // Same status and Content-Type as GET, but with an empty body.
        $this->assertSame('', (string)$headResponse->getBody());
        $this->assertSame(
            $getResponse->getHeaderLine('Content-Type'),
            $headResponse->getHeaderLine('Content-Type'),
        );
    }

    public function testProcess_headDoesNotCollectMetrics()
    {
        // A queue whose size() would throw ensures HEAD never touches the backend.
        $queue = $this->createMock(QueueInterface::class);
        $queue->method('getName')->willReturn('default');
        $queue->method('size')->willThrowException(new RuntimeException('backend must not be queried on HEAD'));

        $app = $this->createMock(HttpApp::class);
        $app->method('getConfig')->willReturn(new Config([new ArrayAdapter($this->enabledConfig())]));
        $app->method('getRoute')->willReturn(null);
        $middleware = new QueueMetricsMiddleware($app, new QueueManager($queue));

        $response = $middleware->process($this->request(method: 'HEAD'), $this->handler());

        $this->assertSame(Response::HTTP_STATUS_OK, $response->getStatusCode());
        $this->assertSame('', (string)$response->getBody());
    }

    public function testProcess_passThroughWhenIpNotAllowed()
    {
        $middleware = $this->middleware($this->enabledConfig(['ip' => ['127.0.0.1']]));
        $middleware->process(
            $this->request(server: ['REMOTE_ADDR' => '203.0.113.10']),
            $handler = $this->handler(),
        );

        $this->assertTrue($handler->handled);
    }

    public function testProcess_servesWhenIpAllowed()
    {
        $middleware = $this->middleware($this->enabledConfig(['ip' => ['127.0.0.1']]));
        $response = $middleware->process(
            $this->request(server: ['REMOTE_ADDR' => '127.0.0.1']),
            $handler = $this->handler(),
        );

        $this->assertFalse($handler->handled);
        $this->assertSame(Response::HTTP_STATUS_OK, $response->getStatusCode());
    }

    public function testProcess_passThroughWhenTokenMissing()
    {
        $middleware = $this->middleware($this->enabledConfig(['token' => 's3cret']));
        $middleware->process($this->request(), $handler = $this->handler());

        $this->assertTrue($handler->handled);
    }

    public function testProcess_passThroughWhenTokenWrong()
    {
        $middleware = $this->middleware($this->enabledConfig(['token' => 's3cret']));
        $middleware->process(
            $this->request(headers: ['Authorization' => 'Bearer nope']),
            $handler = $this->handler(),
        );

        $this->assertTrue($handler->handled);
    }

    public function testProcess_servesWhenTokenMatches()
    {
        $middleware = $this->middleware($this->enabledConfig(['token' => 's3cret']));
        $response = $middleware->process(
            $this->request(headers: ['Authorization' => 'Bearer s3cret']),
            $handler = $this->handler(),
        );

        $this->assertFalse($handler->handled);
        $this->assertSame(Response::HTTP_STATUS_OK, $response->getStatusCode());
    }
}
