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

namespace Berlioz\Package\Twig\Extension;

use Berlioz\Core\Asset\Assets;
use Berlioz\Core\Asset\EntryPoints;
use Berlioz\Core\Asset\Manifest;
use Berlioz\Core\Exception\AssetException;
use Berlioz\Router\Router;
use Throwable;
use Twig\Error\Error;
use Twig\Error\RuntimeError;

class AssetRuntimeExtension
{
    const H2PUSH_CACHE_COOKIE = 'h2pushes';
    private array $h2pushCache = [];

    public function __construct(
        protected Assets $assets,
        protected Router $router,
    ) {
        // Get cache from cookies
        if (isset($_COOKIE[self::H2PUSH_CACHE_COOKIE]) && is_array($_COOKIE[self::H2PUSH_CACHE_COOKIE])) {
            $this->h2pushCache = array_keys($_COOKIE[self::H2PUSH_CACHE_COOKIE]);
        }
    }

    protected function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Function asset to get generate asset path.
     *
     * @param string $key
     * @param Manifest|null $manifest
     *
     * @return string
     * @throws Error
     */
    public function asset(string $key, ?Manifest $manifest = null): string
    {
        try {
            if (null === $manifest) {
                if (null === ($manifest = $this->assets->getManifest())) {
                    throw new RuntimeError('No entry points file');
                }
            }

            if (false === $manifest->has($key)) {
                throw new RuntimeError(sprintf('Asset "%s" not found in manifest file', $key));
            }

            return $this->getRouter()->finalizePath($manifest->get($key));
        } catch (AssetException $exception) {
            throw new RuntimeError('Manifest treatment error', previous: $exception);
        }
    }

    /**
     * Function to get entry points in html.
     *
     * @param string|string[] $entry
     * @param string|null $type
     * @param array $options
     * @param EntryPoints|null $entryPointsObj
     *
     * @return string
     * @throws RuntimeError
     * @throws AssetException
     */
    public function entryPoints(
        string|array $entry,
        ?string $type = null,
        array $options = [],
        ?EntryPoints $entryPointsObj = null
    ): string {
        $output = '';

        if (null === $entryPointsObj) {
            if (null === ($entryPointsObj = $this->assets->getEntryPoints())) {
                throw new RuntimeError('No entry points file');
            }
        }

        $entryPoints = $entryPointsObj->get($entry, $type);

        if (null !== $type) {
            $entryPoints = [$type => $entryPoints];
        }

        foreach ($entryPoints as $type => $entryPointsByType) {
            foreach ($entryPointsByType as $entryPoint) {
                $entryPoint = strip_tags($entryPoint);

                // Preload option
                $preloadOptions = [];
                if (isset($options['preload'])) {
                    if (is_array($options['preload'])) {
                        $preloadOptions = $options['preload'];
                    }
                }

                switch ($type) {
                    case 'js':
                        if (isset($options['preload'])) {
                            $entryPoint = $this->preload(
                                $entryPoint,
                                array_merge(['as' => 'script'], $preloadOptions)
                            );
                            unset($options['preload']);
                        } else {
                            $entryPoint = $this->getRouter()->finalizePath($entryPoint);
                        }

                        $output .= sprintf(
                                '<script%s></script>',
                                $this->attributes(
                                    array_replace(
                                        $options,
                                        ['src' => $entryPoint]
                                    )
                                ),
                            ) . PHP_EOL;
                        break;
                    case 'css':
                        if (isset($options['preload'])) {
                            $entryPoint = $this->preload(
                                $entryPoint,
                                array_merge(['as' => 'style'], $preloadOptions)
                            );
                            unset($options['preload']);
                        } else {
                            $entryPoint = $this->getRouter()->finalizePath($entryPoint);
                        }

                        $output .= sprintf(
                                '<link%s>',
                                $this->attributes(
                                    array_replace(
                                        $options,
                                        [
                                            'rel' => 'stylesheet',
                                            'href' => $entryPoint,
                                        ]
                                    )
                                ),
                            ) . PHP_EOL;
                        break;
                }
            }
        }

        return $output;
    }

    /**
     * Function to get entry points list.
     *
     * @param string|string[] $entry
     * @param string|null $type
     *
     * @return array
     * @throws RuntimeError
     */
    public function entryPointsList(string|array $entry, ?string $type = null): array
    {
        if (null === $this->assets->getEntryPoints()) {
            throw new RuntimeError('No entry points file');
        }

        try {
            $list = $this->assets->getEntryPoints()->get($entry, $type);

            return array_map(
                function ($v) use ($type) {
                    $v = array_map(
                        fn($e) => $this->getRouter()->finalizePath($e),
                        (array)$v
                    );

                    if (null !== $type) {
                        return $v[0];
                    }

                    return $v;
                },
                $list
            );
        } catch (Throwable $exception) {
            throw new RuntimeError('Entry points error', previous: $exception);
        }
    }

    /**
     * Make attributes.
     *
     * @param $attrs
     * @param string|null $prefix
     *
     * @return string
     */
    private function attributes($attrs, ?string $prefix = null): string
    {
        $output = '';

        foreach ($attrs as $key => $value) {
            if (null === $value || false === $value) {
                continue;
            }

            if (!empty($prefix)) {
                $key = $prefix . '-' . $key;
            }

            if (is_array($value)) {
                $output .= $this->attributes($value, $key);
                continue;
            }

            $output .= ' ' . $key . (true !== $value ? '="' . htmlspecialchars($value) . '"' : '');
        }

        return $output;
    }

    /**
     * Function preload to pre loading of request for HTTP 2 protocol.
     *
     * @param string $link
     * @param array $parameters
     *
     * @return string Link
     */
    public function preload(string $link, array $parameters = []): string
    {
        $link = $this->getRouter()->finalizePath($link);

        if (($push = (false === ($parameters['nopush'] ?? false))) && in_array(md5($link), $this->h2pushCache)) {
            return $link;
        }

        $header = sprintf('Link: <%s>; rel=preload', $link);

        foreach ($parameters as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            if (false === $value) {
                continue;
            }

            $header .= '; ' . $key . (true !== $value ? '=' . $value : '');
        }

        if (true === $this->isHeadersSent()) {
            return $link;
        }

        $this->sendHeader($header, false);

        // Cache
        if ($push) {
            $this->h2pushCache[] = md5($link);

            $this->setCookie(
                sprintf('%s[%s]', self::H2PUSH_CACHE_COOKIE, md5($link)),
                '1',
                [
                    'expires' => 0,
                    'path' => '/',
                    'domain' => '',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Strict',
                ]
            );
        }

        return $link;
    }

    /**
     * Is headers sent?
     *
     * @return bool
     */
    public function isHeadersSent(): bool
    {
        return headers_sent();
    }

    /**
     * Send header.
     *
     * @param string $header
     * @param bool $replace
     *
     * @return void
     */
    public function sendHeader(string $header, bool $replace = true): void
    {
        header($header, $replace);
    }

    /**
     * Set cookie.
     *
     * @param string $name
     * @param string $value
     * @param array $options
     *
     * @return void
     */
    public function setCookie(string $name, string $value, array $options = []): void
    {
        setcookie($name, $value, $options);
    }
}