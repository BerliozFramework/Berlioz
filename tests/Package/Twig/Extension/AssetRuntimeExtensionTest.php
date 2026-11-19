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

use Berlioz\Core\Asset\Assets;
use Berlioz\Package\Twig\Extension\AssetRuntimeExtension;
use Berlioz\Router\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Error\RuntimeError;

class AssetRuntimeExtensionTest extends TestCase
{
    private Assets $assets;

    protected function setUp(): void
    {
        unset($_SERVER['HTTP_X_FORWARDED_PREFIX']);
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $this->assets = new Assets(
            __DIR__ . '/data/manifest.json',
            __DIR__ . '/data/entrypoints.json',
        );
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_FORWARDED_PREFIX'], $_SERVER['REMOTE_ADDR']);
    }

    public function testAsset()
    {
        $extensionRuntime = new AssetRuntimeExtension($this->assets, new Router());

        $this->assertEquals('/assets/css/website.css', $extensionRuntime->asset('website.css'));
    }

    public function testAsset_notFound()
    {
        $this->expectException(RuntimeError::class);
        $this->expectExceptionMessage('Asset "fake.css" not found in manifest file');

        $extensionRuntime = new AssetRuntimeExtension($this->assets, new Router());
        $extensionRuntime->asset('fake.css');
    }

    public function testAsset_noManifest()
    {
        $this->expectException(RuntimeError::class);
        $this->expectExceptionMessage('No manifest file');

        $extensionRuntime = new AssetRuntimeExtension(new Assets(), new Router());
        $extensionRuntime->asset('website.css');
    }

    public function testEntryPoints()
    {
        $extensionRuntime = new AssetRuntimeExtension($this->assets, new Router());

        $this->assertEquals(
            '<link rel="stylesheet" href="/assets/css/website.css">' . PHP_EOL .
            '<script src="/assets/js/website.js"></script>' . PHP_EOL .
            '<script src="/assets/js/vendor.js"></script>' . PHP_EOL,
            $extensionRuntime->entryPoints('website')
        );
    }

    public function testEntryPoints_withMultipleEntry()
    {
        $extensionRuntime = new AssetRuntimeExtension($this->assets, new Router());

        $this->assertEquals(
            '<link rel="stylesheet" href="/assets/css/website.css">' . PHP_EOL .
            '<link rel="stylesheet" href="/assets/css/admin.css">' . PHP_EOL .
            '<script src="/assets/js/website.js"></script>' . PHP_EOL .
            '<script src="/assets/js/vendor.js"></script>' . PHP_EOL .
            '<script src="/assets/js/admin.js"></script>' . PHP_EOL,
            $extensionRuntime->entryPoints(['website', 'admin'])
        );
    }

    public function testEntryPoints_withMultipleEntryAndRouterOptions()
    {
        $_SERVER['HTTP_X_FORWARDED_PREFIX'] = '/prefix';
        $extensionRuntime = new AssetRuntimeExtension($this->assets, new Router(['X-Forwarded-Prefix' => true, 'trustedProxies' => ['10.0.0.1']]));

        $this->assertEquals(
            '<link rel="stylesheet" href="/prefix/assets/css/website.css">' . PHP_EOL .
            '<link rel="stylesheet" href="/prefix/assets/css/admin.css">' . PHP_EOL .
            '<script src="/prefix/assets/js/website.js"></script>' . PHP_EOL .
            '<script src="/prefix/assets/js/vendor.js"></script>' . PHP_EOL .
            '<script src="/prefix/assets/js/admin.js"></script>' . PHP_EOL,
            $extensionRuntime->entryPoints(['website', 'admin'])
        );
    }

    public function testEntryPoints_withType()
    {
        $extensionRuntime = new AssetRuntimeExtension($this->assets, new Router());

        $this->assertEquals(
            '<script src="/assets/js/website.js"></script>' . PHP_EOL .
            '<script src="/assets/js/vendor.js"></script>' . PHP_EOL,
            $extensionRuntime->entryPoints('website', 'js')
        );
    }

    public function testEntryPoints_withTypeAndRouterOptions()
    {
        $_SERVER['HTTP_X_FORWARDED_PREFIX'] = '/prefix';
        $extensionRuntime = new AssetRuntimeExtension($this->assets, new Router(['X-Forwarded-Prefix' => true, 'trustedProxies' => ['10.0.0.1']]));

        $this->assertEquals(
            '<script src="/prefix/assets/js/website.js"></script>' . PHP_EOL .
            '<script src="/prefix/assets/js/vendor.js"></script>' . PHP_EOL,
            $extensionRuntime->entryPoints('website', 'js')
        );
    }

    public function testEntryPoints_withOptions()
    {
        $extensionRuntime = new AssetRuntimeExtension($this->assets, new Router());

        $this->assertEquals(
            '<link async defer attr="fake" data-first="value1" data-second="value2" data-third rel="stylesheet" href="/assets/css/website.css">' . PHP_EOL .
            '<script async defer attr="fake" data-first="value1" data-second="value2" data-third src="/assets/js/website.js"></script>' . PHP_EOL .
            '<script async defer attr="fake" data-first="value1" data-second="value2" data-third src="/assets/js/vendor.js"></script>' . PHP_EOL,
            $extensionRuntime->entryPoints('website', options: [
                'async' => true,
                'defer' => true,
                'attr' => 'fake',
                'attr2' => null,
                'data' => [
                    'first' => 'value1',
                    'second' => 'value2',
                    'third' => true,
                    'none' => null,
                ]
            ])
        );
    }

    public static function provideEntryPoints_withPreload(): array
    {
        return [
            'type js' => [
                'entry' => 'website',
                'type' => 'js',
                'options' => ['preload' => true],
                'expectedHtml' =>
                    '<script src="/assets/js/website.js"></script>' . PHP_EOL .
                    '<script src="/assets/js/vendor.js"></script>' . PHP_EOL,
                'expectedHeaders' => [
                    'Link: </assets/js/website.js>; rel=preload; as=script',
                    'Link: </assets/js/vendor.js>; rel=preload; as=script',
                ],
            ],
            'no type' => [
                'entry' => ['website', 'admin'],
                'type' => null,
                'options' => ['preload' => true],
                'expectedHtml' =>
                    '<link rel="stylesheet" href="/assets/css/website.css">' . PHP_EOL .
                    '<link rel="stylesheet" href="/assets/css/admin.css">' . PHP_EOL .
                    '<script src="/assets/js/website.js"></script>' . PHP_EOL .
                    '<script src="/assets/js/vendor.js"></script>' . PHP_EOL .
                    '<script src="/assets/js/admin.js"></script>' . PHP_EOL,
                'expectedHeaders' => [
                    'Link: </assets/css/website.css>; rel=preload; as=style',
                    'Link: </assets/css/admin.css>; rel=preload; as=style',
                    'Link: </assets/js/website.js>; rel=preload; as=script',
                    'Link: </assets/js/vendor.js>; rel=preload; as=script',
                    'Link: </assets/js/admin.js>; rel=preload; as=script',
                ],
            ],
            'preload options' => [
                'entry' => 'website',
                'type' => 'js',
                'options' => ['preload' => ['crossorigin' => true], 'defer' => true],
                'expectedHtml' =>
                    '<script defer src="/assets/js/website.js"></script>' . PHP_EOL .
                    '<script defer src="/assets/js/vendor.js"></script>' . PHP_EOL,
                'expectedHeaders' => [
                    'Link: </assets/js/website.js>; rel=preload; as=script; crossorigin',
                    'Link: </assets/js/vendor.js>; rel=preload; as=script; crossorigin',
                ],
            ],
            'preload disabled' => [
                'entry' => 'website',
                'type' => 'js',
                'options' => ['preload' => false],
                'expectedHtml' =>
                    '<script src="/assets/js/website.js"></script>' . PHP_EOL .
                    '<script src="/assets/js/vendor.js"></script>' . PHP_EOL,
                'expectedHeaders' => [],
            ],
        ];
    }

    #[DataProvider('provideEntryPoints_withPreload')]
    public function testEntryPoints_withPreload(
        string|array $entry,
        ?string $type,
        array $options,
        string $expectedHtml,
        array $expectedHeaders,
    ) {
        $headers = [];
        $extensionRuntime = $this->createPreloadMock($headers);

        $this->assertEquals($expectedHtml, $extensionRuntime->entryPoints($entry, $type, $options));
        $this->assertEquals($expectedHeaders, $headers);
    }

    public function testEntryPoints_notFound()
    {
        $extensionRuntime = new AssetRuntimeExtension($this->assets, new Router());

        $this->assertEquals('', $extensionRuntime->entryPoints('fake'));
    }

    public function testEntryPointsList()
    {
        $extensionRuntime = new AssetRuntimeExtension($this->assets, new Router());

        $this->assertEquals(
            ['css' => ['/assets/css/website.css'], 'js' => ['/assets/js/website.js', '/assets/js/vendor.js']],
            $extensionRuntime->entryPointsList('website')
        );
    }

    public function testEntryPointsList_withMultipleEntry()
    {
        $extensionRuntime = new AssetRuntimeExtension($this->assets, new Router());

        $this->assertEquals(
            ['/assets/js/website.js', '/assets/js/vendor.js', '/assets/js/admin.js'],
            $extensionRuntime->entryPointsList(['website', 'admin'], 'js')
        );
    }

    public function testEntryPointsList_withMultipleEntryAndRouterOptions()
    {
        $_SERVER['HTTP_X_FORWARDED_PREFIX'] = '/prefix';
        $extensionRuntime = new AssetRuntimeExtension($this->assets, new Router(['X-Forwarded-Prefix' => true, 'trustedProxies' => ['10.0.0.1']]));

        $this->assertEquals(
            ['/prefix/assets/js/website.js', '/prefix/assets/js/vendor.js', '/prefix/assets/js/admin.js'],
            $extensionRuntime->entryPointsList(['website', 'admin'], 'js')
        );
    }

    public function testEntryPointsList_withType()
    {
        $extensionRuntime = new AssetRuntimeExtension($this->assets, new Router());

        $this->assertEquals(
            ['/assets/js/website.js', '/assets/js/vendor.js'],
            $extensionRuntime->entryPointsList('website', 'js')
        );
    }

    public function testEntryPointsList_withTypeAndRouterOptions()
    {
        $_SERVER['HTTP_X_FORWARDED_PREFIX'] = '/prefix';
        $extensionRuntime = new AssetRuntimeExtension($this->assets, new Router(['X-Forwarded-Prefix' => true, 'trustedProxies' => ['10.0.0.1']]));

        $this->assertEquals(
            ['/prefix/assets/js/website.js', '/prefix/assets/js/vendor.js'],
            $extensionRuntime->entryPointsList('website', 'js')
        );
    }

    public function testEntryPointsList_notFound()
    {
        $extensionRuntime = new AssetRuntimeExtension($this->assets, new Router());

        $this->assertEquals([], $extensionRuntime->entryPointsList('fake'));
    }

    public static function providesPreload()
    {
        return [
            [
                'link' => 'https://getberlioz.com/fake',
                'parameters' => [
                    'crossorigin' => false,
                ],
                'expectedHeader' => 'Link: <https://getberlioz.com/fake>; rel=preload',
            ],
            [
                'link' => 'https://getberlioz.com/fake',
                'parameters' => [
                    'crossorigin' => true,
                ],
                'expectedHeader' => 'Link: <https://getberlioz.com/fake>; rel=preload; crossorigin',
            ],
            [
                'link' => '/assets/css/website.css',
                'parameters' => [
                    'as' => 'style',
                ],
                'expectedHeader' => 'Link: </assets/css/website.css>; rel=preload; as=style',
            ],
        ];
    }

    #[DataProvider('providesPreload')]
    public function testPreload(string $link, array $parameters, string $expectedHeader)
    {
        $headers = [];
        $assetRuntimeMock = $this->createPreloadMock($headers);

        $result = $assetRuntimeMock->preload($link, $parameters);

        $this->assertEquals($link, $result);
        $this->assertEquals([$expectedHeader], $headers);
    }

    public function testPreload_ignoresH2PushCookie()
    {
        $_COOKIE['h2pushes'] = [md5('/assets/css/website.css') => '1'];

        try {
            $headers = [];
            $assetRuntimeMock = $this->createPreloadMock($headers);
            $assetRuntimeMock->preload('/assets/css/website.css', ['as' => 'style']);
        } finally {
            unset($_COOKIE['h2pushes']);
        }

        $this->assertEquals(['Link: </assets/css/website.css>; rel=preload; as=style'], $headers);
    }

    public function testPreload_onceByLink()
    {
        $headers = [];
        $assetRuntimeMock = $this->createPreloadMock($headers);

        $this->assertEquals('/assets/js/website.js', $assetRuntimeMock->preload('/assets/js/website.js'));
        $this->assertEquals('/assets/js/website.js', $assetRuntimeMock->preload('/assets/js/website.js'));
        $assetRuntimeMock->preload('/assets/js/vendor.js');

        $this->assertEquals(
            [
                'Link: </assets/js/website.js>; rel=preload',
                'Link: </assets/js/vendor.js>; rel=preload',
            ],
            $headers
        );
    }

    public function testPreload_headersSent()
    {
        $assetRuntimeMock = $this->getMockBuilder(AssetRuntimeExtension::class)
            ->setConstructorArgs([$this->assets, new Router()])
            ->onlyMethods(['isHeadersSent', 'sendHeader', 'setCookie'])
            ->getMock();
        $assetRuntimeMock->method('isHeadersSent')->willReturn(true);
        $assetRuntimeMock->expects($this->never())->method('sendHeader');
        $assetRuntimeMock->expects($this->never())->method('setCookie');

        $this->assertEquals('/assets/js/website.js', $assetRuntimeMock->preload('/assets/js/website.js'));
    }

    public function testPreload_nopushDeprecated()
    {
        $headers = [];
        $assetRuntimeMock = $this->createPreloadMock($headers);

        $deprecationMessage = null;
        set_error_handler(function (int $errno, string $errstr) use (&$deprecationMessage): bool {
            if (E_USER_DEPRECATED !== $errno) {
                return false;
            }

            $deprecationMessage = $errstr;

            return true;
        });

        try {
            $assetRuntimeMock->preload('https://getberlioz.com/fake', ['nopush' => true, 'crossorigin' => true]);
        } finally {
            restore_error_handler();
        }

        $this->assertEquals(
            'The "nopush" preload parameter is deprecated, HTTP/2 Server Push is no longer supported',
            $deprecationMessage
        );
        $this->assertEquals(['Link: <https://getberlioz.com/fake>; rel=preload; nopush; crossorigin'], $headers);
    }

    private function createPreloadMock(array &$headers): AssetRuntimeExtension
    {
        $assetRuntimeMock = $this->getMockBuilder(AssetRuntimeExtension::class)
            ->setConstructorArgs([$this->assets, new Router()])
            ->onlyMethods(['isHeadersSent', 'sendHeader', 'setCookie'])
            ->getMock();
        $assetRuntimeMock
            ->method('isHeadersSent')
            ->willReturn(false);
        $assetRuntimeMock
            ->method('sendHeader')
            ->willReturnCallback(function (string $header) use (&$headers): void {
                $headers[] = $header;
            });
        $assetRuntimeMock
            ->expects($this->never())
            ->method('setCookie');

        return $assetRuntimeMock;
    }
}
