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

namespace Berlioz\ServiceContainer\Container;

use Berlioz\ServiceContainer\Container;
use Berlioz\ServiceContainer\Exception\NotFoundException;
use Berlioz\ServiceContainer\Provider\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

class ProviderContainer implements ContainerInterface
{
    /** @var ServiceProviderInterface[] */
    protected array $providers = [];
    protected array $registered = [];

    public function __construct(protected Container $container)
    {
    }

    /**
     * Add provider.
     *
     * @param ServiceProviderInterface ...$provider
     */
    public function addProvider(ServiceProviderInterface ...$provider): void
    {
        array_push($this->providers, ...$provider);
        array_walk($provider, fn(ServiceProviderInterface $provider) => $provider->boot($this->container));
    }

    /**
     * @inheritDoc
     */
    public function get(string $id): mixed
    {
        foreach ($this->providers as $iProvider => $provider) {
            if (false === $provider->provides($id)) {
                continue;
            }

            array_push($this->registered, ...array_splice($this->providers, $iProvider, 1));
            $provider->register($this->container);

            return $this->container->get($id);
        }

        throw NotFoundException::notFound($id);
    }

    /**
     * @inheritDoc
     */
    public function has(string $id): bool
    {
        foreach ($this->providers as $provider) {
            if (true === $provider->provides($id)) {
                return true;
            }
        }

        return false;
    }
}