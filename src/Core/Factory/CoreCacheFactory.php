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

namespace Berlioz\Core\Factory;

use Berlioz\Config\ConfigInterface;
use Berlioz\Config\Exception\ConfigException;
use Berlioz\Core\Composer\Composer;
use Berlioz\Core\Core;
use Berlioz\Core\Exception\BerliozException;
use Berlioz\Core\Exception\CacheException;
use Berlioz\Core\Filesystem\FilesystemInterface;
use Berlioz\Core\Package\PackageSet;
use DateTimeImmutable;
use Exception;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemException;
use League\Flysystem\StorageAttributes;
use Psr\SimpleCache\CacheException as PsrCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Class CoreCacheFactory.
 */
class CoreCacheFactory extends CoreFactory
{
    protected const CACHE_TIME = 'BERLIOZ/@TIME';
    protected const CACHE_COMPOSER = 'BERLIOZ/COMPOSER';
    protected const CACHE_CONFIG = 'BERLIOZ/CONFIG';
    protected const CACHE_PACKAGES = 'BERLIOZ/PACKAGES';

    /**
     * Get item from cache.
     *
     * @param string $key
     * @param string $excepted
     *
     * @return mixed
     * @throws CacheException
     */
    private function getItemFromCache(string $key, string $excepted): mixed
    {
        try {
            if (false === $this->core->getCache()->has($key)) {
                return null;
            }

            if (false === (($item = $this->core->getCache()->get($key)) instanceof $excepted)) {
                throw new CacheException(sprintf('Cache integrity for item "%s"', $key));
            }
        } catch (InvalidArgumentException $exception) {
            throw new CacheException(sprintf('Cache error for item "%s"', $key), 0, $exception);
        }

        return $item;
    }

    /**
     * Get cache time.
     *
     * @return DateTimeImmutable|null
     * @throws InvalidArgumentException
     */
    private function getCacheTime(): ?DateTimeImmutable
    {
        $time = $this->core->getCache()->get(static::CACHE_TIME);

        if (!($time instanceof DateTimeImmutable)) {
            return null;
        }

        return $time;
    }

    /**
     * Save to cache.
     *
     * @return bool
     * @throws BerliozException
     * @throws PsrCacheException
     */
    private function saveToCache(): bool
    {
        if (null === $this->composer || null === $this->config || null === $this->packages) {
            throw new BerliozException('Unable to save Berlioz Core to cache');
        }

        return
            $this->core->getCache()->setMultiple(
                [
                    static::CACHE_TIME => new DateTimeImmutable(),
                    static::CACHE_COMPOSER => $this->composer,
                    static::CACHE_CONFIG => $this->config,
                    static::CACHE_PACKAGES => $this->packages,
                ]
            );
    }

    /**
     * Get last modified time file.
     *
     * Check config files, composer.json and composer.lock.
     *
     * @return DateTimeImmutable|null
     * @throws FilesystemException
     * @throws Exception
     */
    private function getLastModifiedTime(): ?DateTimeImmutable
    {
        // Configuration files
        $fs = $this->core->getFilesystem();
        $lastModified =
            $fs->listContents('config://', FilesystemInterface::LIST_DEEP)
                ->filter(fn(StorageAttributes $attr) => $attr->isFile())
                ->map(fn(FileAttributes $attr) => $fs->lastModified($attr->path()))
                ->toArray();

        // Composer file
        if ($this->core->getFilesystem()->fileExists('app://composer.lock')) {
            $lastModified[] = $this->core->getFilesystem()->lastModified('app://composer.lock');
        }

        $lastModified = array_filter($lastModified);

        if (count($lastModified) === 0) {
            return null;
        }

        return new DateTimeImmutable(sprintf('@%d', max($lastModified)));
    }

    /**
     * @inheritDoc
     * @throws FilesystemException
     * @throws PsrCacheException
     */
    public function build(): void
    {
        try {
            $this->config = $this->getItemFromCache(static::CACHE_CONFIG, ConfigInterface::class);
            $this->composer = $this->getItemFromCache(static::CACHE_COMPOSER, Composer::class);
            $this->packages = $this->getItemFromCache(static::CACHE_PACKAGES, PackageSet::class);

            // Only if all components are gotten from cache
            if (null !== $this->config && null !== $this->composer && null !== $this->packages) {
                // It's not a development environment, so we keep cache and do not check files time!
                if ($this->config->get('berlioz.environment') != Core::ENV_DEV) {
                    return;
                }

                // Cache time is more older than last modified file time
                if ($this->getCacheTime() > $this->getLastModifiedTime()) {
                    return;
                }
            }

            // Reset and build core director
            parent::reset();
            parent::build();

            $this->saveToCache();
        } catch (ConfigException $exception) {
            throw new BerliozException('Configuration error', 0, $exception);
        } catch (CacheException|InvalidArgumentException $exception) {
            throw new BerliozException('Corrupted cache', 0, $exception);
        }
    }
}
