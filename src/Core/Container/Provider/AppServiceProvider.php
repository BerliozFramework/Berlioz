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

namespace Berlioz\Core\Container\Provider;

use Berlioz\Config\Exception\ConfigException;
use Berlioz\Core\Core;
use Berlioz\Core\Exception\BerliozException;
use Berlioz\Core\Exception\ConfigException as BConfigException;
use Berlioz\ServiceContainer\Container;
use Berlioz\ServiceContainer\Provider\AbstractServiceProvider;
use Berlioz\ServiceContainer\Service\CacheStrategy;
use Berlioz\ServiceContainer\Service\Service;
use DateInterval;
use Exception;
use Throwable;

/**
 * Class AppServiceProvider.
 */
class AppServiceProvider extends AbstractServiceProvider
{
    protected array $provides = [];

    /**
     * AppServiceProvider constructor.
     *
     * @throws BerliozException
     */
    public function __construct(protected Core $core)
    {
        try {
            foreach ((array)$this->core->getConfig()->get('container.services', []) as $alias => $serviceConfig) {
                if (is_string($serviceConfig)) {
                    $this->provides[] = $alias;
                    $this->provides[] = $serviceConfig;
                    continue;
                }

                // No class defined or not an array
                if (!is_array($serviceConfig) || empty($serviceConfig['class'])) {
                    continue;
                }

                $this->provides[] = $alias;
                $this->provides[] = $serviceConfig['class'];
                array_push($this->provides, ...(array)($serviceConfig['provides'] ?? []));
            }
        } catch (ConfigException $exception) {
            throw new BerliozException('Service container configuration error', 0, $exception);
        }
    }

    /**
     * @inheritDoc
     * @throws BerliozException
     */
    public function register(Container $container): void
    {
        try {
            foreach ((array)$this->core->getConfig()->get('container.services', []) as $alias => $serviceConfig) {
                try {
                    // Only defined class name
                    if (is_string($serviceConfig)) {
                        $container->add(class: $serviceConfig, alias: $alias);
                        continue;
                    }

                    // No class defined or not an array
                    if (!is_array($serviceConfig)) {
                        continue;
                    }

                    // Service cache strategy
                    $cacheStrategy = null;
                    if (isset($serviceConfig['cache']) && false !== $serviceConfig['cache']) {
                        $cacheStrategy = new CacheStrategy(
                            $this->core->getCache(),
                            $this->getCacheTtl($serviceConfig['cache'])
                        );
                    }

                    // Add service
                    $service = new Service(
                        class: $serviceConfig['class'] ?? $alias,
                        alias: $alias,
                        factory: $serviceConfig['factory'] ?? null,
                        cacheStrategy: $cacheStrategy
                    );
                    $service->addArguments($serviceConfig['arguments'] ?? []);
                    if (!empty($serviceConfig['calls'])) {
                        foreach ($serviceConfig['calls'] as $call) {
                            $service->addCall(
                                $call['method'] ?? throw BConfigException::serviceConfig($alias),
                                $call['arguments'] ?? throw BConfigException::serviceConfig($alias),
                            );
                        }
                    }
                    $service->addProvide(...(array)($serviceConfig['provides'] ?? []));
                    $container->addService($service);
                } catch (Throwable $exception) {
                    throw new BConfigException(sprintf('Error into "%s" service configuration', $alias), 0, $exception);
                }
            }
        } catch (ConfigException $exception) {
            throw new BConfigException('Service container configuration error', 0, $exception);
        }
    }

    /**
     * @inheritDoc
     */
    public function boot(Container $container): void
    {
    }

    /**
     * Get cache TTL.
     *
     * @param mixed $config
     *
     * @return DateInterval|int|null
     * @throws Exception
     */
    protected function getCacheTtl(mixed $config): DateInterval|int|null
    {
        if (is_string($config)) {
            return new DateInterval($config);
        }

        if (is_numeric($config)) {
            return (int)$config;
        }

        return null;
    }
}