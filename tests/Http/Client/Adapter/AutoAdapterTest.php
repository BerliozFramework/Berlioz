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

namespace Berlioz\Http\Client\Tests\Adapter;

use Berlioz\Http\Client\Adapter\AutoAdapter;
use Berlioz\Http\Client\Adapter\CurlAdapter;
use Berlioz\Http\Client\Adapter\StreamAdapter;
use Berlioz\Http\Message\Response;
use Berlioz\Http\Message\ServerRequest;
use PHPUnit\Framework\TestCase;

class AutoAdapterTest extends TestCase
{
    public function testResolveAdapterPrefersCurlWhenExtensionLoaded()
    {
        if (!extension_loaded('curl')) {
            $this->markTestSkipped('cURL extension is not loaded');
        }

        $curl = new FakeAdapter(fn() => new Response());
        $stream = new FakeAdapter(fn() => new Response());

        $adapter = new AutoAdapter(curl: $curl, stream: $stream);

        $this->assertSame($curl, $adapter->resolveAdapter());
    }

    public function testResolveAdapterFallbacksToStreamWhenCurlMissing()
    {
        if (extension_loaded('curl')) {
            $this->markTestSkipped('cURL extension is loaded');
        }

        $stream = new FakeAdapter(fn() => new Response());

        $adapter = new AutoAdapter(stream: $stream);

        $this->assertSame($stream, $adapter->resolveAdapter());
    }

    public function testDefaultResolvedAdapterType()
    {
        $adapter = new AutoAdapter();

        $this->assertInstanceOf(
            extension_loaded('curl') ? CurlAdapter::class : StreamAdapter::class,
            $adapter->resolveAdapter(),
        );
    }

    public function testGetNameDelegates()
    {
        $resolved = new FakeAdapter(fn() => new Response());
        $adapter = new AutoAdapter(curl: $resolved, stream: $resolved);

        $this->assertSame('fake', $adapter->getName());
    }

    public function testSendRequestDelegates()
    {
        $request = new ServerRequest(method: 'GET', uri: 'https://getberlioz.com/');
        $expected = new Response();

        $called = false;
        $resolved = new FakeAdapter(function ($req) use (&$called, $request, $expected) {
            $called = true;
            $this->assertSame($request, $req);

            return $expected;
        });

        $adapter = new AutoAdapter(curl: $resolved, stream: $resolved);
        $response = $adapter->sendRequest($request);

        $this->assertTrue($called);
        $this->assertSame($expected, $response);
    }

    public function testGetTimingsDelegates()
    {
        $resolved = new FakeAdapter(fn() => new Response());
        $adapter = new AutoAdapter(curl: $resolved, stream: $resolved);

        $this->assertNull($adapter->getTimings());
    }

    public function testSerialization()
    {
        $adapter = new AutoAdapter();
        $unserialized = unserialize(serialize($adapter));

        $this->assertInstanceOf(AutoAdapter::class, $unserialized);
        $this->assertInstanceOf(
            extension_loaded('curl') ? CurlAdapter::class : StreamAdapter::class,
            $unserialized->resolveAdapter(),
        );
    }
}
