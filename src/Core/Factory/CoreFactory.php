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

use Berlioz\Config\Config;
use Berlioz\Core\Composer\Composer;
use Berlioz\Core\Composer\ComposerBuilder;
use Berlioz\Core\Config\ConfigBuilder;
use Berlioz\Core\Container\ContainerBuilder;
use Berlioz\Core\Core;
use Berlioz\Core\Exception\BerliozException;
use Berlioz\Core\Package\PackageBuilder;
use Berlioz\Core\Package\PackageSet;
use Berlioz\ServiceContainer\Container;
use Throwable;

/**
 * Class CoreFactory.
 */
class CoreFactory implements CoreFactoryInterface
{
    protected ComposerBuilder $composerBuilder;
    protected ConfigBuilder $configBuilder;
    protected PackageBuilder $packageBuilder;
    protected ContainerBuilder $containerBuilder;

    protected ?Composer $composer = null;
    protected ?Config $config = null;
    protected ?PackageSet $packages = null;
    protected ?Container $container = null;

    public function __construct(protected Core $core)
    {
        $this->composerBuilder = new ComposerBuilder($this->core->getFilesystem());
        $this->configBuilder = new ConfigBuilder($this->core->getFilesystem());
        $this->packageBuilder = new PackageBuilder();
        $this->containerBuilder = new ContainerBuilder($this->core);

        $this->reset();
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        $this->composerBuilder->reset();
        $this->configBuilder->reset();
        $this->packageBuilder->reset();
        $this->containerBuilder->reset();
    }

    /**
     * @inheritDoc
     * @throws BerliozException
     */
    public function build(): void
    {
        try {
            // Build composer
            $this->composerBuilder->build();

            // Build default and project configurations
            $this->configBuilder->initVariables($this->core->getDirectories());
            $this->configBuilder->addDefaultConfig();
            $this->configBuilder->addProjectConfig();

            // Build packages, need default and project configuration!
            $this->packageBuilder->addComposerPackages($this->composerBuilder->getComposer());
            $this->packageBuilder->addConfigPackages($this->configBuilder->getConfig());

            // Build packages configurations
            $this->configBuilder->addPackagesConfig($this->packageBuilder->getPackages());

            $this->composer = $this->composerBuilder->getComposer();
            $this->config = $this->configBuilder->getConfig();
            $this->packages = $this->packageBuilder->getPackages();
        } catch (BerliozException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new BerliozException('Core boot error', previous: $exception);
        }
    }

    /**
     * @inheritDoc
     */
    public function getComposer(): Composer
    {
        return $this->composer;
    }

    /**
     * @inheritDoc
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * @inheritDoc
     */
    public function getPackages(): PackageSet
    {
        return $this->packages;
    }
}
