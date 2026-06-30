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

namespace Berlioz\Http\Core\Tests\App;

use Berlioz\FlashBag\FlashBag;
use Berlioz\Http\Core\App\AppProfile;
use Berlioz\Http\Core\Tests\AbstractTestCase;
use Berlioz\Http\Message\ServerRequest;
use Locale;

class AppProfileTest extends AbstractTestCase
{
    public function getAppAndProfile(): array
    {
        $profile = new AppProfile($app = $this->getApp());

        return [$app, $profile];
    }

    public function testDebugInfo()
    {
        [1 => $profile] = $this->getAppAndProfile();

        $this->assertEmpty($profile->__debugInfo());
    }

    public function testGetEnv()
    {
        [$app, $profile] = $this->getAppAndProfile();

        $this->assertSame($app->getCore()->getEnv(), $profile->getEnv());
    }

    public function testGetConfig()
    {
        [$app, $profile] = $this->getAppAndProfile();

        $this->assertSame($app->getConfig(), $profile->getConfig());
    }

    public function testGetAssets()
    {
        [$app, $profile] = $this->getAppAndProfile();

        $this->assertSame($app->getAssets(), $profile->getAssets());
    }

    public function testGetFlashBag()
    {
        [$app, $profile] = $this->getAppAndProfile();

        $this->assertSame($app->get(FlashBag::class), $profile->getFlashBag());
    }

    public function testGetRequest_NULL()
    {
        [$app, $profile] = $this->getAppAndProfile();

        $this->assertNull($app->getRequest());
        $this->assertSame($app->getRequest(), $profile->getRequest());
    }

    public function testGetRequest()
    {
        [$app, $profile] = $this->getAppAndProfile();
        $app->handle($request = new ServerRequest('GET', '/controller1/method1?foo=bar&qux=quux'));

        $this->assertSame($request, $app->getRequest());
        $this->assertSame($app->getRequest(), $profile->getRequest());
    }

    public function testGetRoute_NULL()
    {
        [$app, $profile] = $this->getAppAndProfile();

        $this->assertNull($app->getRoute());
        $this->assertSame($app->getRoute(), $profile->getRoute());
    }

    public function testGetRoute()
    {
        [$app, $profile] = $this->getAppAndProfile();
        $app->handle(new ServerRequest('GET', '/controller1/method1?foo=bar&qux=quux'));

        $this->assertEquals('c1m1', $app->getRoute()->getName());
        $this->assertSame($app->getRoute(), $profile->getRoute());
    }

    public function testGetLocale()
    {
        [$app, $profile] = $this->getAppAndProfile();
        $app->handle(new ServerRequest('GET', '/controller1/method1?foo=bar&qux=quux'));

        $this->assertEquals(Locale::getDefault(), $app->getCore()->getLocale());
        $this->assertSame($app->getCore()->getLocale(), $profile->getLocale());
    }

    public function testIsDebugEnabled_enabled()
    {
        [$app, $profile] = $this->getAppAndProfile();
        $app->getCore()->getDebug()->setEnabled(true);

        $this->assertTrue($app->getCore()->getDebug()->isEnabled());
        $this->assertSame($app->getCore()->getDebug()->isEnabled(), $profile->isDebugEnabled());
    }

    public function testIsDebugEnabled_disabled()
    {
        [$app, $profile] = $this->getAppAndProfile();

        $this->assertFalse($app->getCore()->getDebug()->isEnabled());
        $this->assertSame($app->getCore()->getDebug()->isEnabled(), $profile->isDebugEnabled());
    }

    public function testGetDebugUniqid()
    {
        [$app, $profile] = $this->getAppAndProfile();

        $this->assertNotEmpty($app->getCore()->getDebug()->getUniqid());
        $this->assertSame($app->getCore()->getDebug()->getUniqid(), $profile->getDebugUniqid());
    }
}
