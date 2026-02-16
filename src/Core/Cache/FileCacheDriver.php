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

namespace Berlioz\Core\Cache;

use Berlioz\Core\Directories\DirectoriesInterface;
use Berlioz\Core\Exception\CacheException;
use DateInterval;
use Exception;
use Psr\SimpleCache\CacheInterface;

/**
 * Class FileCacheDriver.
 */
class FileCacheDriver extends AbstractCacheDriver implements CacheInterface
{
    public const CACHE_DIRECTORY = 'berlioz';

    /**
     * CacheManager constructor.
     *
     * @param DirectoriesInterface $directories
     */
    public function __construct(protected DirectoriesInterface $directories)
    {
    }

    /**
     * @inheritDoc
     * @throws CacheException
     */
    public function has(string $key): bool
    {
        $this->controlKey($key);
        $cacheFilename = $this->getFilename($key);

        if (null === ($content = $this->getCacheFileContents($cacheFilename))) {
            return false;
        }

        return $this->isValidTtl($content['ttl']);
    }

    /**
     * @inheritDoc
     * @throws CacheException
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->controlKey($key);
        $cacheFilename = $this->getFilename($key);

        if (null === ($content = $this->getCacheFileContents($cacheFilename))) {
            return $default;
        }

        try {
            if ($this->isValidTtl($content['ttl'])) {
                if (($unserializedData = @unserialize($content['data'] ?? null)) === false) {
                    throw new CacheException(
                        sprintf('Corrupted data for key "%s" from cache "%s"', $key, $cacheFilename)
                    );
                }

                return $unserializedData;
            }
        } catch (Exception $e) {
            throw new CacheException('TTL cache exception', 0, $e);
        }

        return $default;
    }

    /**
     * @inheritDoc
     * @throws CacheException
     */
    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->controlKey($key);
        $cacheFilename = $this->getFilename($key);

        if (($serialized = serialize($value)) === false) {
            throw new CacheException(sprintf('Unable to serialize data to cache save "%s"', $key));
        }

        $data = [
            'ttl' => $this->makeTtl($ttl),
            'data' => $serialized,
        ];

        if (!is_dir($directory = dirname($cacheFilename))) {
            if (@mkdir($directory, 0777, true) === false) {
                throw new CacheException(sprintf('Unable to write cache file "%s"', $cacheFilename));
            }
        }

        if (@file_put_contents($cacheFilename, @serialize($data)) === false) {
            throw new CacheException(sprintf('Unable to save file to cache "%s"', $cacheFilename));
        }

        return true;
    }

    /**
     * @inheritDoc
     * @throws CacheException
     */
    public function delete(string $key): bool
    {
        $this->controlKey($key);
        $cacheFilename = $this->getFilename($key);

        if (!file_exists($cacheFilename)) {
            return true;
        }

        if (@unlink($cacheFilename) === true) {
            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        $cacheDir =
            $this->directories->getCacheDir() .
            DIRECTORY_SEPARATOR .
            static::CACHE_DIRECTORY;

        if (is_dir($cacheDir)) {
            return $this->rmdir($cacheDir);
        }

        return true;
    }

    /**
     * Get filename from name.
     *
     * @param string $name
     *
     * @return string|null
     */
    private function getFilename(string $name): ?string
    {
        $name = md5($name);

        return
            $this->directories->getCacheDir() .
            DIRECTORY_SEPARATOR .
            static::CACHE_DIRECTORY .
            DIRECTORY_SEPARATOR .
            substr($name, 0, 2) .
            DIRECTORY_SEPARATOR .
            $name .
            '.txt';
    }

    /**
     * Get cache file contents.
     *
     * @param string $filename
     *
     * @return array|null
     * @throws CacheException
     */
    private function getCacheFileContents(string $filename): ?array
    {
        if (!file_exists($filename)) {
            return null;
        }

        if (false === ($content = @file_get_contents($filename))) {
            throw new CacheException(sprintf('Unable to read file from cache "%s"', $filename));
        }

        if (false === ($unserialized = @unserialize($content)) || !is_array($unserialized)) {
            throw new CacheException(sprintf('Corrupted data in cache file "%s"', $filename));
        }

        return $unserialized;
    }

    /**
     * Remove recursively directory.
     *
     * @param string $dir
     *
     * @return bool
     */
    private function rmdir(string $dir): bool
    {
        $dir = rtrim($dir, '\\/');

        if (($files = scandir($dir)) === false) {
            return false;
        }

        $result = true;
        foreach ($files as $file) {
            if (in_array($file, ['.', '..'])) {
                continue;
            }

            // Full filename
            $file = $dir . DIRECTORY_SEPARATOR . $file;

            if (is_dir($file)) {
                if ($this->rmdir($file) === false) {
                    $result = false;
                }
                continue;
            }

            if (@unlink($file) === false) {
                $result = false;
            }
        }

        if ($result) {
            if (@rmdir($dir) === false) {
                $result = false;
            }
        }

        return $result;
    }
}