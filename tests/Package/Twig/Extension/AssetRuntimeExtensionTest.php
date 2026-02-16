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
        $this->assets = new Assets(
            __DIR__ . '/data/manifest.json',
            __DIR__ . '/data/entrypoints.json',
        );
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
        $extensionRuntime = new AssetRuntimeExtension($this->assets, new Router(['X-Forwarded-Prefix' => true]));

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
        $extensionRuntime = new AssetRuntimeExtension($this->assets, new Router(['X-Forwarded-Prefix' => true]));

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
        $extensionRuntime = new AssetRuntimeExtension($this->assets, new Router(['X-Forwarded-Prefix' => true]));

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
        $extensionRuntime = new AssetRuntimeExtension($this->assets, new Router(['X-Forwarded-Prefix' => true]));

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
                'expectedCookie' => [
                    'h2pushes[d51704684c8d3f1febc7c281cc2c8f26]',
                    '1',
                    [
                        'expires' => 0,
                        'path' => '/',
                        'domain' => '',
                        'secure' => true,
                        'httponly' => true,
                        'samesite' => 'Strict',
                    ]
                ],
            ],
            [
                'link' => 'https://getberlioz.com/fake',
                'parameters' => [
                    'crossorigin' => true,
                ],
                'expectedHeader' => 'Link: <https://getberlioz.com/fake>; rel=preload; crossorigin',
                'expectedCookie' => [
                    'h2pushes[d51704684c8d3f1febc7c281cc2c8f26]',
                    '1',
                    [
                        'expires' => 0,
                        'path' => '/',
                        'domain' => '',
                        'secure' => true,
                        'httponly' => true,
                        'samesite' => 'Strict',
                    ]
                ],
            ],
            [
                'link' => 'https://getberlioz.com/fake',
                'parameters' => [
                    'nopush' => true,
                    'crossorigin' => true,
                ],
                'expectedHeader' => 'Link: <https://getberlioz.com/fake>; rel=preload; nopush; crossorigin',
                'expectedCookie' => null,
            ]
        ];
    }

    #[DataProvider('providesPreload')]
    public function testPreload(string $link, array $parameters, ?string $expectedHeader, ?array $expectedCookie)
    {
        $headerArguments = $cookieArguments = null;

        $assetRuntimeMock = $this->createPartialMock(
            AssetRuntimeExtension::class,
            ['getRouter', 'isHeadersSent', 'sendHeader', 'setCookie']
        );
        $assetRuntimeMock
            ->method('getRouter')
            ->willReturnCallback(fn() => new Router());
        $assetRuntimeMock
            ->method('isHeadersSent')
            ->willReturnCallback(fn() => false);
        $assetRuntimeMock
            ->method('sendHeader')
            ->willReturnCallback(function ($header) use (&$headerArguments) {
                $headerArguments = $header;
            });
        $assetRuntimeMock
            ->method('setCookie')
            ->willReturnCallback(function (...$args) use (&$cookieArguments) {
                $cookieArguments = $args;
            });

        $result = $assetRuntimeMock->preload($link, $parameters);

        $this->assertEquals($link, $result);
        $this->assertEquals($expectedHeader, $headerArguments);
        $this->assertEquals($expectedCookie, $cookieArguments);
    }
}
