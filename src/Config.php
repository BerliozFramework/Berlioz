<?php
/**
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2017 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\Core;


use Berlioz\Core\Exception\BerliozException;
use Psr\Log\LogLevel;

/**
 * Class Config.
 *
 * Offer basic configuration class to manage JSON configuration files.
 * Access to the values with get() method, uses 'key.subkey.something' for example.
 *
 * @package Berlioz\Core
 */
class Config implements ConfigInterface
{
    /** @var string Root directory */
    private $rootDirectory;
    /** @var string Configuration directory */
    private $configDirectory;
    /** @var array Configuration */
    private $configuration;

    /**
     * @inheritdoc
     */
    public function __construct(string $rootDir, string $fileName)
    {
        if (($this->rootDirectory = realpath($rootDir)) !== false) {
            // Define configuration file
            $this->setConfigDirectory(dirname($fileName));

            // Load configuration
            $this->configuration = $this->load(basename($fileName));
        } else {
            throw new \InvalidArgumentException(sprintf('Directory "%s" does not exists', $rootDir));
        }
    }

    /**
     * Set configuration directory.
     *
     * @param string $dirName Path of directory
     *
     * @return void
     * @throws \InvalidArgumentException If directory doesn't exists
     */
    private function setConfigDirectory(string $dirName): void
    {
        $dirName = realpath($this->getDirectory(Config::DIR_ROOT) . $dirName);

        if (is_dir($dirName)) {
            $this->configDirectory = $dirName;
        } else {
            if ($dirName !== false) {
                throw new \InvalidArgumentException(sprintf('Directory "%s" does not exists', $dirName));
            } else {
                throw new \InvalidArgumentException(sprintf('Config directory does not exists', $dirName));
            }
        }
    }

    /**
     * Load configuration.
     *
     * @param string $file File name
     *
     * @return array
     * @throws \Berlioz\Core\Exception\BerliozException If unable to load configuration file
     */
    private function load(string $file): array
    {
        $file = basename($file);
        $fileName = realpath($this->configDirectory . '/' . $file);

        try {
            $json = @file_get_contents($fileName);

            if ($json !== false) {
                $configuration = json_decode($json, true);

                if (!empty($configuration)) {
                    if (!empty($configuration['@extends'])) {
                        $extends = $configuration['@extends'];
                        unset($configuration['@extends']);

                        $configuration = array_replace_recursive($this->load($extends), $configuration);
                    }
                } else {
                    throw new BerliozException(sprintf('Not a valid JSON configuration file "%s"', $file));
                }
            } else {
                throw new BerliozException(sprintf('Unable to load configuration file "%s"', $file));
            }
        } catch (BerliozException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new BerliozException(sprintf('Unable to load configuration file "%s"', $file));
        }

        return $configuration;
    }

    /**
     * @inheritdoc
     */
    public function get(string $key = null)
    {
        $key = explode('.', $key);

        return b_array_traverse($this->configuration, $key);
    }

    /**
     * @inheritdoc
     */
    public function has(string $key = null): bool
    {
        try {
            $key = explode('.', $key);
            b_array_traverse($this->configuration, $key, $exists);
        } catch (\Exception $e) {
            $exists = false;
        }

        return $exists;
    }

    /**
     * @inheritdoc
     */
    public function hasDebugEnabled(): bool
    {
        return $this->get('app.debug') == true;
    }

    /**
     * @inheritdoc
     */
    public function hasCacheEnabled(): bool
    {
        return $this->get('app.cache') == true;
    }

    /**
     * @inheritdoc
     */
    public function getLogLevel(): string
    {
        if (in_array($this->get('app.log'),
                     [LogLevel::EMERGENCY,
                      LogLevel::ALERT,
                      LogLevel::CRITICAL,
                      LogLevel::ERROR,
                      LogLevel::WARNING,
                      LogLevel::NOTICE,
                      LogLevel::INFO,
                      LogLevel::DEBUG],
                     true)) {
            return $this->get('app.log');
        } else {
            return LogLevel::EMERGENCY;
        }
    }

    /**
     * @inheritdoc
     */
    public function getDirectory($directory = ConfigInterface::DIR_ROOT)
    {
        switch ($directory) {
            case ConfigInterface::DIR_ROOT:
                return $this->rootDirectory;
            case ConfigInterface::DIR_CORE:
                return $this->getDirectory(ConfigInterface::DIR_ROOT) . '/src';
            case ConfigInterface::DIR_VAR:
                return $this->getDirectory(ConfigInterface::DIR_ROOT) . '/var';
            case ConfigInterface::DIR_VAR_CACHE:
                return $this->getDirectory(ConfigInterface::DIR_VAR) . '/cache';
            case ConfigInterface::DIR_VAR_FILES:
                return $this->getDirectory(ConfigInterface::DIR_VAR) . '/files';
            case ConfigInterface::DIR_VAR_LOGS:
                return $this->getDirectory(ConfigInterface::DIR_VAR) . '/logs';
            case ConfigInterface::DIR_VAR_TMP:
                return $this->getDirectory(ConfigInterface::DIR_VAR) . '/tmp';
            case ConfigInterface::DIR_VENDOR:
                return $this->getDirectory(ConfigInterface::DIR_ROOT) . '/vendor';
            default:
                return false;
        }
    }
}