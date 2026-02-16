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

namespace Berlioz\Core\Config;

use Berlioz\Config\Adapter\AdapterInterface;
use Berlioz\Config\Adapter\ConfigBridgeAdapter;
use Berlioz\Config\Adapter\IniAdapter;
use Berlioz\Config\Adapter\JsonAdapter;
use Berlioz\Config\Adapter\YamlAdapter;
use Berlioz\Config\Config;
use Berlioz\Config\Exception\ConfigException;
use Berlioz\Core\Directories\DirectoriesInterface;
use Berlioz\Core\Exception\BerliozException;
use Berlioz\Core\Filesystem\FilesystemInterface;
use Berlioz\Core\Package\PackageSet;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemException;
use League\Flysystem\StorageAttributes;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;
use League\MimeTypeDetection\GeneratedExtensionToMimeTypeMap;
use Throwable;

/**
 * Class ConfigBuilder.
 */
class ConfigBuilder
{
    const PRIORITY_CORE = -100;
    const PRIORITY_PACKAGE = 0;
    const PRIORITY_PROJECT = 100;
    private Config $config;
    private ?ExtensionMimeTypeDetector $mimeTypeDetector = null;

    public function __construct(protected FilesystemInterface $fs)
    {
        $this->reset();
    }

    /**
     * Reset builder.
     */
    public function reset(): void
    {
        $this->config = new Config();
    }

    /**
     * Get config.
     *
     * @return Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Init variables.
     *
     * @param DirectoriesInterface $directories
     *
     * @return static
     */
    public function initVariables(DirectoriesInterface $directories): static
    {
        $variables = $this->config->getVariables();

        $variables['berlioz.directories.working'] = $directories->getWorkingDir();
        $variables['berlioz.directories.app'] = $directories->getAppDir();
        $variables['berlioz.directories.config'] = $directories->getConfigDir();
        $variables['berlioz.directories.cache'] = $directories->getCacheDir();
        $variables['berlioz.directories.log'] = $directories->getLogDir();
        $variables['berlioz.directories.debug'] = $directories->getDebugDir();
        $variables['berlioz.directories.var'] = $directories->getVarDir();
        $variables['berlioz.directories.vendor'] = $directories->getVendorDir();

        return $this;
    }

    /**
     * Add default configuration (from default configuration).
     *
     * @return static
     * @throws BerliozException
     */
    public function addDefaultConfig(): static
    {
        try {
            $this->config->addConfig(
                new JsonAdapter(
                    str: __DIR__ . '/../resources/config.default.json',
                    strIsUrl: true,
                    priority: static::PRIORITY_CORE
                )
            );
        } catch (ConfigException $e) {
            throw new BerliozException('Default configuration error', 0, $e);
        }

        return $this;
    }

    /**
     * Add packages configuration.
     *
     * @param PackageSet $packages
     *
     * @return static
     * @throws ConfigException
     */
    public function addPackagesConfig(PackageSet $packages): static
    {
        $this->config->addConfig(new ConfigBridgeAdapter($packages->config(), static::PRIORITY_PACKAGE));

        return $this;
    }

    /**
     * Add project configuration.
     *
     * @return static
     * @throws BerliozException
     */
    public function addProjectConfig(): static
    {
        try {
            // Add project configuration
            $configs =
                $this->fs
                    ->listContents('config://', FilesystemInterface::LIST_DEEP)
                    ->filter(fn(StorageAttributes $attr) => $attr->isFile())
                    ->map(fn(FileAttributes $attr) => $this->getProjectConfigFile($attr));

            $this->config->addConfig(...array_filter($configs->toArray()));
        } catch (Throwable $e) {
            throw new BerliozException('Project configuration error', 0, $e);
        }

        return $this;
    }

    /**
     * Add project config file.
     *
     * @param FileAttributes $attr
     *
     * @return AdapterInterface|null
     * @throws ConfigException
     * @throws FilesystemException
     */
    private function getProjectConfigFile(FileAttributes $attr): ?AdapterInterface
    {
        $priority = (static::PRIORITY_PROJECT - (int)str_contains($attr->path(), '.dist'));

        switch ($this->getMimeTypeDetector()->detectMimeTypeFromPath($attr->path())) {
            case 'application/json':
            case 'application/json5':
                return new JsonAdapter(
                    $this->fs->read($attr->path()),
                    priority: $priority
                );
            case 'text/yaml':
                return new YamlAdapter(
                    $this->fs->read($attr->path()),
                    priority: $priority
                );
            default:
                if (str_ends_with($attr->path(), '.ini')) {
                    return new IniAdapter(
                        $this->fs->read($attr->path()),
                        priority: $priority
                    );
                }
        }

        return null;
    }

    /**
     * Get mime type detector.
     *
     * @return ExtensionMimeTypeDetector
     */
    private function getMimeTypeDetector(): ExtensionMimeTypeDetector
    {
        if (null !== $this->mimeTypeDetector) {
            return $this->mimeTypeDetector;
        }

        return $this->mimeTypeDetector = new ExtensionMimeTypeDetector(new GeneratedExtensionToMimeTypeMap());
    }
}
