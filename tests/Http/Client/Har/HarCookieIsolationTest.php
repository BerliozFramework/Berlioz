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

namespace Berlioz\Http\Client\Tests\Har;

use Berlioz\Http\Client\Adapter\HarAdapter;
use Berlioz\Http\Client\Cookies\CookiesManager;
use Berlioz\Http\Client\Har\HarFactory;
use Berlioz\Http\Client\Session;
use Berlioz\Http\Message\Request;
use Berlioz\Http\Message\Response;
use ElGigi\HarParser\Entities\Log;
use ElGigi\HarParser\Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HarCookieIsolationTest extends TestCase
{
    private function export(Session $session): Log
    {
        return (new Parser())->parse(json_encode(HarFactory::createHarFromSession($session), JSON_THROW_ON_ERROR));
    }

    public function testResponseCookiesPreserveScopeAcrossRoundTrips(): void
    {
        $session = new Session();
        $request = new Request('GET', 'https://example.test/account/login');
        $response = new Response(null, 200, ['Set-Cookie' => [
            'host=secret; Secure; SameSite=Strict',
            'domain=shared; Domain=example.test; Path=/',
        ]]);
        $session->getHistory()->add($session->getCookies(), $request, $response);
        $session->getCookies()->addCookiesFromResponse($request->getUri(), $response);

        for ($roundTrip = 0; $roundTrip < 2; $roundTrip++) {
            $session = HarFactory::createSession($this->export($session));
            $cookies = $session->getCookies();
            $this->assertCount(2, $cookies);
            $target = new Request('GET', 'https://example.test/account/profile');
            $this->assertSame('host=secret; domain=shared', $cookies->addCookiesToRequest($target)->getHeaderLine('Cookie'));
            $this->assertTrue($cookies->getCookie('host', $target->getUri())->isHostOnly());
            $this->assertSame('Strict', $cookies->getCookie('host', $target->getUri())->getSameSite());
            foreach (['https://sub.example.test/account/profile', 'https://example.test/other',
                'http://example.test/account/profile'] as $uri) {
                $this->assertSame(
                    'domain=shared',
                    $cookies->addCookiesToRequest(new Request('GET', $uri))->getHeaderLine('Cookie'),
                );
            }
        }
    }

    public function testInitialRequestCookiesPreserveScope(): void
    {
        $session = new Session();
        $request = new Request('GET', 'https://example.test/');
        $session->getCookies()->addRawCookie('host=secret; Path=/', $request->getUri());
        $session->getCookies()->addRawCookie('domain=shared; Domain=example.test; Path=/', $request->getUri());
        $session->getHistory()->add($session->getCookies(), $request, new Response());

        $restored = HarFactory::createSession($this->export($session));
        $this->assertSame('host=secret; domain=shared', $restored->getLastRequest()->getHeaderLine('Cookie'));
        $this->assertSame(
            'domain=shared',
            $restored->getCookies()->addCookiesToRequest(
                new Request('GET', 'https://sub.example.test/'),
            )->getHeaderLine('Cookie'),
        );
    }

    public static function provideInvalidDomains(): iterable
    {
        yield 'malformed' => ['..example.test'];
        yield 'unrelated' => ['victim.test'];
    }

    #[DataProvider('provideInvalidDomains')]
    public function testInvalidResponseCookiesAreSkippedDuringImportAndReplay(string $domain): void
    {
        $session = new Session();
        $request = new Request('GET', 'https://example.test/');
        $response = new Response(null, 200, ['Set-Cookie' => [
            'bad=secret; Domain=' . $domain,
            'good=value; Path=/',
        ]]);
        $session->getHistory()->add($session->getCookies(), $request, $response);
        $har = $this->export($session);

        $restored = HarFactory::createSession($har);
        $this->assertCount(1, $restored->getCookies());
        $this->assertSame('good=value', $restored->getCookies()->addCookiesToRequest($request)->getHeaderLine('Cookie'));

        $replayed = (new HarAdapter($har))->sendRequest($request);
        $cookies = new CookiesManager();
        $cookies->addCookiesFromResponse($request->getUri(), $replayed);
        $this->assertCount(1, $replayed->getHeader('Set-Cookie'));
        $this->assertSame('good=value', $cookies->addCookiesToRequest($request)->getHeaderLine('Cookie'));
    }

    public function testAmbiguousAndInvalidInitialHarCookies(): void
    {
        $session = new Session();
        $request = new Request('GET', 'https://example.test/');
        $session->getHistory()->add($session->getCookies(), $request, new Response());
        $data = json_decode(json_encode($this->export($session), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        $data['log']['entries'][0]['request']['cookies'] = [
            ['name' => 'old', 'value' => 'secret', 'domain' => 'example.test'],
            ['name' => 'missing', 'value' => 'secret'],
            ['name' => 'third', 'value' => 'secret', 'domain' => '.victim.test'],
            ['name' => 'bad', 'value' => 'secret', 'domain' => '..example.test'],
        ];

        $restored = HarFactory::createSession((new Parser())->parse($data));
        $this->assertCount(2, $restored->getCookies());
        $this->assertSame('old=secret; missing=secret', $restored->getLastRequest()->getHeaderLine('Cookie'));
        $this->assertSame('', $restored->getCookies()->addCookiesToRequest(
            new Request('GET', 'https://sub.example.test/'),
        )->getHeaderLine('Cookie'));
    }
}
