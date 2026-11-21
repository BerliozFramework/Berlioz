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

namespace Berlioz\Http\Core\Tests\Http\Middleware;

use Berlioz\Http\Core\Attribute\Route as RouteAttribute;
use Berlioz\Http\Core\Exception\HttpAppException;
use Berlioz\Http\Core\Http\Middleware\AcceptQueryMiddleware;
use Berlioz\Http\Core\Tests\AbstractTestCase;
use Berlioz\Http\Core\Tests\Http\FakeRequestHandler;
use Berlioz\Http\Message\ServerRequest;
use Berlioz\Router\Route;
use PHPUnit\Framework\Attributes\DataProvider;

class AcceptQueryMiddlewareTest extends AbstractTestCase
{
    private function process(
        ?Route $route,
        string $method = 'QUERY',
        ?string $contentType = null,
        ?FakeRequestHandler &$handler = null,
    ) {
        $app = $this->getApp();
        $app->setRoute($route);
        $request = new ServerRequest($method, '/contacts');
        if (null !== $contentType) {
            $request = $request->withHeader('Content-Type', $contentType);
        }

        return (new AcceptQueryMiddleware($app))->process($request, $handler = new FakeRequestHandler());
    }

    public function testProcess_noRoute(): void
    {
        $response = $this->process(null, contentType: 'text/plain', handler: $handler);

        $this->assertTrue($handler->isHandled());
        $this->assertFalse($response->hasHeader('Accept-Query'));
    }

    public function testProcess_noOption(): void
    {
        $response = $this->process(new Route('/contacts'), handler: $handler);

        $this->assertTrue($handler->isHandled());
        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($response->hasHeader('Accept-Query'));
    }

    public function testProcess_optionFromAttributeVariadic(): void
    {
        $route = (new RouteAttribute('/contacts', method: ['GET', 'QUERY'], acceptQuery: 'application/json'))
            ->getRoute();

        $response = $this->process($route, 'GET', handler: $handler);

        $this->assertTrue($handler->isHandled());
        $this->assertSame('"application/json"', $response->getHeaderLine('Accept-Query'));
    }

    public function testProcess_optionInheritedFromGroup(): void
    {
        $group = new Route('/api', options: ['acceptQuery' => ['application/json']]);
        $group->addRoute($route = new Route('/contacts'));

        $response = $this->process($route, 'HEAD');

        $this->assertSame('"application/json"', $response->getHeaderLine('Accept-Query'));
    }

    public static function provideAcceptedContentTypes(): array
    {
        return [
            'exact' => [['application/json'], 'application/json'],
            'case insensitive' => [['application/json'], 'Application/JSON'],
            'with parameters' => [['application/json'], 'application/json; charset=utf-8'],
            'type wildcard' => [['application/*'], 'application/sql'],
            'full wildcard' => [['*/*'], 'text/plain'],
            'range parameters ignored' => [['application/sql;charset=UTF-8'], 'application/sql'],
            'one of list' => [['application/json', 'text/plain'], 'text/plain'],
        ];
    }

    #[DataProvider('provideAcceptedContentTypes')]
    public function testProcess_accepted(array $mediaRanges, string $contentType): void
    {
        $route = new Route('/contacts', options: ['acceptQuery' => $mediaRanges]);
        $response = $this->process($route, contentType: $contentType, handler: $handler);

        $this->assertTrue($handler->isHandled());
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('Accept-Query'));
    }

    public function testProcess_missingContentType(): void
    {
        $route = new Route('/contacts', options: ['acceptQuery' => ['application/json']]);
        $response = $this->process($route, handler: $handler);

        $this->assertFalse($handler->isHandled());
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('"application/json"', $response->getHeaderLine('Accept-Query'));
    }

    public static function provideUnsupportedContentTypes(): array
    {
        return [
            'other type' => [['application/json'], 'application/xml'],
            'type wildcard' => [['text/*'], 'application/json'],
            'suffix' => [['application/json'], 'application/vnd.api+json'],
        ];
    }

    #[DataProvider('provideUnsupportedContentTypes')]
    public function testProcess_unsupportedContentType(array $mediaRanges, string $contentType): void
    {
        $route = new Route('/contacts', options: ['acceptQuery' => $mediaRanges]);
        $response = $this->process($route, contentType: $contentType, handler: $handler);

        $this->assertFalse($handler->isHandled());
        $this->assertSame(415, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('Accept-Query'));
    }

    public function testProcess_otherMethodNotValidated(): void
    {
        $route = new Route('/contacts', options: ['acceptQuery' => ['application/json']]);
        $response = $this->process($route, 'POST', 'application/xml', $handler);

        $this->assertTrue($handler->isHandled());
        $this->assertSame(200, $response->getStatusCode());
    }

    public static function provideHeaderSerialization(): array
    {
        return [
            'single' => ['application/json', '"application/json"'],
            'list' => [['application/jsonpath', 'application/sql'], '"application/jsonpath", "application/sql"'],
            'lowercase' => ['Application/SQL', '"application/sql"'],
            'parameter' => ['application/sql; charset=UTF-8', '"application/sql";charset="UTF-8"'],
            'quoted parameter' => ['application/sql;Charset="UTF-8"', '"application/sql";charset="UTF-8"'],
            'escaped parameter' => ['text/plain; foo="a\\"b"', '"text/plain";foo="a\\"b"'],
            'leading digit' => ['application/3gpp-ims+xml', '"application/3gpp-ims+xml"'],
        ];
    }

    #[DataProvider('provideHeaderSerialization')]
    public function testProcess_headerSerialization(string|array $option, string $expected): void
    {
        $route = new Route('/contacts', options: ['acceptQuery' => $option]);
        $response = $this->process($route, 'GET');

        $this->assertSame($expected, $response->getHeaderLine('Accept-Query'));
    }

    public static function provideInvalidOptions(): array
    {
        return [
            'not a string' => [[42]],
            'not a media type' => [['json']],
            'invalid parameter name' => [['application/json; 1charset=utf-8']],
            'non ascii parameter value' => [['application/json; charset=é']],
        ];
    }

    #[DataProvider('provideInvalidOptions')]
    public function testProcess_invalidOption(array $option): void
    {
        $this->expectException(HttpAppException::class);

        $this->process(new Route('/contacts', options: ['acceptQuery' => $option]), 'GET');
    }
}
