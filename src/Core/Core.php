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

namespace Berlioz\Core;

use Berlioz\Config\Config;
use Berlioz\Config\Exception\ConfigException;
use Berlioz\Core\Cache\CacheManager;
use Berlioz\Core\Composer\Composer;
use Berlioz\Core\Container\ContainerBuilder;
use Berlioz\Core\Debug\DebugHandler;
use Berlioz\Core\Debug\Snapshot\TimelineActivity as TimelineActivity;
use Berlioz\Core\Debug\SnapshotLoader;
use Berlioz\Core\Directories\DefaultDirectories;
use Berlioz\Core\Directories\DirectoriesInterface;
use Berlioz\Core\Event\EventDispatcher;
use Berlioz\Core\Exception\BerliozException;
use Berlioz\Core\Factory\CoreCacheFactory;
use Berlioz\Core\Filesystem\FilesystemInterface;
use Berlioz\Core\Package\PackageSet;
use Berlioz\ServiceContainer\Container;
use Locale;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\CacheInterface;
use Throwable;

/**
 * Class Core.
 */
class Core
{
    public const ENV_PROD = 'prod';
    public const ENV_DEV = 'dev';

    protected DebugHandler $debugHandler;
    protected DirectoriesInterface $directories;
    protected Filesystem\FilesystemInterface $filesystem;
    protected CacheInterface $cache;
    protected Composer $composer;
    protected Config $config;
    protected PackageSet $packages;
    protected Container $container;
    protected EventDispatcher $eventDispatcher;

    /**
     * Core constructor.
     *
     * @param DirectoriesInterface|null $directories
     * @param CacheInterface|bool $cache
     *
     * @throws BerliozException
     */
    public function __construct(?DirectoriesInterface $directories = null, CacheInterface|bool $cache = true)
    {
        $this->directories = $directories ?? new DefaultDirectories();
        $this->cache = new CacheManager($cache, $this->directories);

        // Boot
        $this->boot();
    }

    public function __destruct()
    {
        // Save debug report
        if ($this->debugHandler->isEnabled()) {
            try {
                $snapshotLoader = new SnapshotLoader($this->filesystem, $this->config ?? null);
                $snapshotLoader->save($this->debugHandler->getSnapshot());
            } catch (Throwable) {
                trigger_error('Unable to save debug snapshot', E_USER_WARNING);
            }
        }
    }

    /**
     * PHP serialize method.
     *
     * @throws BerliozException
     */
    public function __serialize(): array
    {
        throw new BerliozException(sprintf('Serialization of class "%s" not allowed', static::class));
    }

    /**
     * Boot.
     *
     * @throws BerliozException
     */
    public function boot()
    {
        try {
            // Debug
            $phpActivity = new TimelineActivity('PHP initialization', 'Berlioz');
            $phpActivity->start($_SERVER['REQUEST_TIME_FLOAT'] ?? null)->end();
            $bootActivity = (new TimelineActivity('Boot', 'Berlioz'))->start();

            // Debug handler
            $this->debugHandler = new DebugHandler();
            $this->debugHandler->handle($this);

            // Filesystem
            $this->filesystem = new Filesystem\BerliozFilesystem($this->directories);

            // Core components
            $coreFactory = new CoreCacheFactory($this);
            $coreFactory->build();
            $this->composer = $coreFactory->getComposer();
            $this->config = $coreFactory->getConfig();
            $this->packages = $coreFactory->getPackages();

            // Filesystem - Final
            $this->filesystem = new Filesystem\Filesystem(
                $this->filesystem,
                new Filesystem\ProjectFilesystem($this->config),
            );

            // Container
            $containerBuilder = new ContainerBuilder($this);
            $containerBuilder->addDefaultProviders();
            $containerBuilder->addProvidersFromConfig();
            $this->container = $containerBuilder->getContainer();

            // Get locale from config if not null (default value)
            if (null !== ($locale = $this->config->get('berlioz.locale'))) {
                if (Locale::setDefault($locale) !== true) {
                    throw new BerliozException(sprintf('Unable to set locale "%s", not valid', $locale));
                }
            }

            // Register packages
            $packagesActivity = (new TimelineActivity('Packages', 'Berlioz'))->start();
            $this->packages->register($this->container);

            // Activate debug
            $this->debugHandler->setEnabled($this->debugHandler->isEnabledInConfig($this->getConfig()));
            $this->debugHandler->addActivity(
                $phpActivity,
                $bootActivity->end(),
                $packagesActivity
            );

            // Boot packages
            $this->packages->boot($this);
            $packagesActivity->end();
        } catch (Throwable|CacheException $exception) {
            throw new BerliozException('Berlioz boot error', 0, $exception);
        }
    }

    ///////////////
    /// GETTERS ///
    ///////////////

    /**
     * Get environment.
     *
     * @return string
     * @throws ConfigException
     */
    public function getEnv(): string
    {
        return $this->getConfig()->get('berlioz.environment', static::ENV_DEV);
    }

    /**
     * Get debug handler.
     *
     * @return DebugHandler
     */
    public function getDebug(): DebugHandler
    {
        return $this->debugHandler;
    }

    /**
     * Get cache manager.
     *
     * @return CacheManager
     */
    public function getCache(): CacheManager
    {
        return $this->cache;
    }

    /**
     * Get directories.
     *
     * @return DirectoriesInterface
     */
    public function getDirectories(): DirectoriesInterface
    {
        return $this->directories;
    }

    /**
     * Get filesystem.
     *
     * @return FilesystemInterface
     */
    public function getFilesystem(): FilesystemInterface
    {
        return $this->filesystem;
    }

    /**
     * Get composer.
     *
     * @return Composer
     */
    public function getComposer(): Composer
    {
        return $this->composer;
    }

    /**
     * Get configuration.
     *
     * @return Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Get packages set.
     *
     * @return PackageSet
     */
    public function getPackages(): PackageSet
    {
        return $this->packages;
    }

    /**
     * Get container.
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Get event dispatcher.
     *
     * @return EventDispatcher
     */
    public function getEventDispatcher(): EventDispatcher
    {
        return $this->container->get(EventDispatcher::class);
    }

    /**
     * Get locale.
     *
     * @return string
     * @see Locale
     */
    public function getLocale(): string
    {
        return Locale::getDefault();
    }
}
