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

namespace Berlioz\Http\Core\Tests\Controller;

use Berlioz\Config\Adapter\ArrayAdapter;
use Berlioz\Core\Core;
use Berlioz\Core\Debug\DebugHandler;
use Berlioz\Core\Debug\SnapshotLoader;
use Berlioz\Core\Tests\RestoresErrorHandler;
use Berlioz\Http\Core\App\HttpApp;
use Berlioz\Http\Core\TestProject\FakeDefaultDirectories;
use Berlioz\Http\Message\ServerRequest;
use PHPUnit\Framework\TestCase;

class DebugControllerTest extends TestCase
{
    use RestoresErrorHandler;

    private function getApp(): HttpApp
    {
        $core = new Core(new FakeDefaultDirectories(), false);
        $core->getConfig()->addConfig(new ArrayAdapter(
            [
                'berlioz' => ['debug' => ['enable' => true]],
                'twig' => ['paths' => ['Berlioz-HttpCore' => realpath(__DIR__ . '/../../../../src/Http/Core/resources')]],
            ],
            priority: PHP_INT_MAX,
        ));

        return new HttpApp($core);
    }

    private function getLoader(HttpApp $app): SnapshotLoader
    {
        return new SnapshotLoader($app->getCore()->getFilesystem(), $app->getConfig());
    }

    public function testToolbar_ready(): void
    {
        $app = $this->getApp();
        $handler = new DebugHandler();
        $handler->handle($app->getCore());
        $handler->setEnabled(true);
        $handler->newActivity('Foo')->start(10.0)->end(10.5);
        $handler->markResponseSent(10.25);
        $this->getLoader($app)->save($handler->getSnapshot());

        $response = $app->handle(new ServerRequest('GET', '/_console/' . $handler->getUniqid() . '/toolbar'));

        $body = (string)$response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('250 ms', $body);
        $this->assertStringContainsString('bi-check2-circle', $body);
        $this->assertStringNotContainsString('window.location.replace', $body);
    }

    public function testToolbar_pending(): void
    {
        $app = $this->getApp();
        $uniqid = uniqid();
        $this->getLoader($app)->markPending($uniqid, ['responseTime' => 0.0421]);

        $response = $app->handle(new ServerRequest('GET', '/_console/' . $uniqid . '/toolbar'));
        $body = (string)$response->getBody();

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame(['no-store'], $response->getHeader('Cache-Control'));
        $this->assertStringContainsString('bi-clock', $body);
        $this->assertStringContainsString('42.1 ms', $body);
        $this->assertStringContainsString('window.location.replace', $body);
    }

    public function testDashboard_pending(): void
    {
        $app = $this->getApp();
        $uniqid = uniqid();
        $this->getLoader($app)->markPending($uniqid, ['responseTime' => 0.0421]);

        $response = $app->handle(new ServerRequest('GET', '/_console/' . $uniqid));
        $body = (string)$response->getBody();

        $this->assertSame(202, $response->getStatusCode());
        $this->assertStringContainsString('Snapshot in progress', $body);
        $this->assertStringContainsString('data-report="' . $uniqid . '"', $body);
        $this->assertStringContainsString('<strong>42.1 ms</strong>, but', $body);
        $this->assertStringContainsString('window.location.replace', $body);
        $this->assertStringNotContainsString('id="sidebar"', $body);
    }

    public function testDashboard_abandoned(): void
    {
        $app = $this->getApp();
        $uniqid = uniqid();
        $app->getCore()->getFilesystem()->write(
            sprintf('debug://%s.pending', $uniqid),
            json_encode(['uniqid' => $uniqid, 'expire' => microtime(true) - 1]),
        );

        $response = $app->handle(new ServerRequest('GET', '/_console/' . $uniqid . '/activities'));
        $body = (string)$response->getBody();

        $this->assertSame(410, $response->getStatusCode());
        $this->assertStringContainsString('Snapshot incomplete', $body);
        $this->assertStringNotContainsString('window.location.replace', $body);
    }

    public function testToolbar_abandoned(): void
    {
        $app = $this->getApp();
        $uniqid = uniqid();
        $app->getCore()->getFilesystem()->write(
            sprintf('debug://%s.pending', $uniqid),
            json_encode(['uniqid' => $uniqid, 'expire' => microtime(true) - 1]),
        );

        $response = $app->handle(new ServerRequest('GET', '/_console/' . $uniqid . '/toolbar'));
        $body = (string)$response->getBody();

        $this->assertSame(410, $response->getStatusCode());
        $this->assertStringContainsString('class="danger"', $body);
        $this->assertStringContainsString('bi-x-circle-fill', $body);
        $this->assertStringNotContainsString('window.location.replace', $body);
    }

    public function testDashboard_missing(): void
    {
        $app = $this->getApp();

        $response = $app->handle(new ServerRequest('GET', '/_console/' . uniqid() . '/activities'));

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testActivities_responseSentMarker(): void
    {
        $app = $this->getApp();
        $handler = new DebugHandler();
        $handler->handle($app->getCore());
        $handler->setEnabled(true);
        $handler->newActivity('Foo')->start(10.0)->end(10.5);
        $handler->markResponseSent(10.25);
        $this->getLoader($app)->save($handler->getSnapshot());

        $response = $app->handle(new ServerRequest('GET', '/_console/' . $handler->getUniqid() . '/activities'));
        $body = (string)$response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Response sent after 250 ms', $body);
        $this->assertStringContainsString('left: 50%', $body);
        $this->assertStringContainsString('id="sidebar"', $body);
        $this->assertStringContainsString('data-report="' . $handler->getUniqid() . '"', $body);
    }
}
