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

namespace Berlioz\Http\Client\Tests;

use Berlioz\Http\Client\Har\HarFactory;
use Berlioz\Http\Client\Cookies\Cookie;
use Berlioz\Http\Client\Cookies\CookiesManager;
use Berlioz\Http\Client\Session;
use Berlioz\Http\Message\Request;
use Berlioz\Http\Message\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class SessionTest extends TestCase
{
    public function testSerializationPreservesCookieScope(): void
    {
        $source = new Uri('https', 'example.com');
        $cookies = new CookiesManager([
            Cookie::parse('host=value; Path=/', $source),
            Cookie::parse('domain=value; Domain=example.com; Path=/', $source),
        ]);
        $session = new Session(name: 'test', cookies: $cookies);
        $this->assertSame($cookies, $session->getCookies());
        $saved = serialize($session);
        $this->assertStringNotContainsString(CookiesManager::class, $saved);
        $restored = unserialize($saved);

        $this->assertSame('test', $restored->getName());
        $request = $restored->getCookies()->addCookiesToRequest(new Request('GET', 'https://example.com/'));
        $this->assertSame('host=value; domain=value', $request->getHeaderLine('Cookie'));
        $request = $restored->getCookies()->addCookiesToRequest(new Request('GET', 'https://sub.example.com/'));
        $this->assertSame('domain=value', $request->getHeaderLine('Cookie'));
    }

    public function testRestorationDiscardsLegacyManagerCookies(): void
    {
        $cookies = new CookiesManager();
        $cookies->addRawCookie('sid=value; Domain=example.com');
        $session = new Session();
        $session->__unserialize(['name' => 'legacy', 'cookies' => $cookies]);

        $this->assertSame('legacy', $session->getName());
        $this->assertCount(0, $session->getCookies());
    }

    public function testGetLastRequest()
    {
        $session = HarFactory::createSessionFromFile(__DIR__ . '/../../../vendor/elgigi/har-parser/tests/example.har');

        $this->assertInstanceOf(RequestInterface::class, $session->getLastRequest());
        $this->assertSame($session->getHistory()->getLast()->getRequest(), $session->getLastRequest());
    }

    public function testGetLastRequest_none()
    {
        $session = new Session();

        $this->assertNull($session->getLastRequest());
    }

    public function testGetLastResponse()
    {
        $session = HarFactory::createSessionFromFile(__DIR__ . '/../../../vendor/elgigi/har-parser/tests/example.har');

        $this->assertInstanceOf(ResponseInterface::class, $session->getLastResponse());
        $this->assertSame($session->getHistory()->getLast()->getResponse(), $session->getLastResponse());
    }

    public function testGetLastResponse_none()
    {
        $session = new Session();

        $this->assertNull($session->getLastResponse());
    }
}
