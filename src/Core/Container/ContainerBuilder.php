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

namespace Berlioz\Core\Container;

use Berlioz\Core\Core;
use Berlioz\Core\Exception\BerliozException;
use Berlioz\Core\Exception\ConfigException as BConfigException;
use Berlioz\ServiceContainer\Container;
use Throwable;

/**
 * Class ContainerBuilder.
 */
class ContainerBuilder
{
    protected Container $container;

    /**
     * ContainerBuilder constructor.
     *
     * @param Core $core
     */
    public function __construct(protected Core $core)
    {
        $this->reset();
    }

    /**
     * Reset.
     */
    public function reset(): void
    {
        $this->container = new Container();
        $this->container->autoWiring(true);
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
     * Add default providers.
     *
     * @throws BerliozException
     */
    public function addDefaultProviders(): void
    {
        $this->container->addProvider(
            new Provider\CoreServiceProvider($this->core),
            new Provider\AppServiceProvider($this->core)
        );
    }

    /**
     * Add providers from config.
     *
     * @throws BerliozException
     */
    public function addProvidersFromConfig(): void
    {
        try {
            $providers = (array)$this->core->getConfig()->get('container.providers', []);
            array_walk(
                $providers,
                function (&$provider) {
                    if (false === is_string($provider)) {
                        throw BConfigException::serviceProvidersConfig();
                    }

                    $provider = $this->container->call($provider);
                }
            );

            $this->container->addProvider(...$providers);
        } catch (BerliozException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw BConfigException::serviceProvidersConfig($exception);
        }
    }
}