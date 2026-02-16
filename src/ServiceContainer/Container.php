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

namespace Berlioz\ServiceContainer;

use Berlioz\ServiceContainer\Container\AutoWiringContainer;
use Berlioz\ServiceContainer\Container\DefaultContainer;
use Berlioz\ServiceContainer\Container\ProviderContainer;
use Berlioz\ServiceContainer\Exception\NotFoundException;
use Berlioz\ServiceContainer\Inflector\Inflector;
use Berlioz\ServiceContainer\Provider\ServiceProviderInterface;
use Berlioz\ServiceContainer\Service\Service;
use Closure;
use Generator;
use Psr\Container\ContainerInterface;

class Container implements ContainerInterface
{
    protected Instantiator $instantiator;
    protected ContainerInterface $container;
    protected ProviderContainer $providers;
    /** @var ContainerInterface[] */
    protected array $containers = [];
    /** @var Inflector[] */
    protected array $inflectors = [];

    /**
     * Container constructor.
     *
     * @param ContainerInterface[] $containers
     * @param Inflector[] $inflectors
     */
    public function __construct(array $containers = [], array $inflectors = [])
    {
        $this->instantiator = new Instantiator($this);
        $this->container = new DefaultContainer($this->instantiator);
        $this->providers = new ProviderContainer($this);

        $this->addContainer(...$containers);
        $this->addInflector(
            new Inflector(
                ContainerAwareInterface::class,
                'setContainer',
                ['container' => $this]
            ),
            new Inflector(
                InstantiatorAwareInterface::class,
                'setInstantiator',
                ['instantiator' => $this->instantiator]
            ),
            ...$inflectors
        );

        // Add me has container service
        $service = $this->add($this);
        $service->addProvide(ContainerInterface::class);
    }

    /**
     * Auto wiring.
     *
     * @param bool $active
     */
    public function autoWiring(bool $active = true): void
    {
        if (true === $active) {
            $this->addContainer(new AutoWiringContainer($this->instantiator));
            return;
        }

        // Remove auto wiring container
        $this->containers = array_filter(
            $this->containers,
            fn($container) => !($container instanceof AutoWiringContainer)
        );
    }

    /**
     * Add service.
     *
     * @param Service ...$service
     */
    public function addService(Service ...$service): void
    {
        $this->container->addService(...$service);
    }

    /**
     * New service.
     *
     * @param string|object $class
     * @param string|null $alias
     *
     * @return Service
     */
    public function add(string|object $class, ?string $alias = null): Service
    {
        if ($class instanceof Service) {
            $this->addService($class);

            return $class;
        }

        $this->addService($service = new Service($class, $alias));

        return $service;
    }

    /**
     * @inheritDoc
     * @template T
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    public function get(string $id): mixed
    {
        /** @var ContainerInterface $container */
        foreach ($this->getContainers() as $container) {
            if (true === $container->has($id)) {
                $result = $container->get($id);

                if (!is_object($result)) {
                    return $result;
                }

                return $this->inflects($result);
            }
        }

        throw NotFoundException::notFound($id);
    }

    /**
     * @inheritDoc
     */
    public function has(string $id): bool
    {
        /** @var ContainerInterface $container */
        foreach ($this->getContainers() as $container) {
            if (true === $container->has($id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Call.
     *
     * @param string|array|Closure $subject
     * @param array $arguments
     * @param bool $autoWiring
     *
     * @return mixed
     * @throws Exception\ContainerException
     */
    public function call(string|array|Closure $subject, array $arguments = [], bool $autoWiring = true): mixed
    {
        $result = $this->instantiator->call($subject, $arguments, $autoWiring);

        if (!is_object($result)) {
            return $result;
        }

        return $this->inflects($result);
    }

    /**
     * Get containers.
     *
     * @return Generator<ContainerInterface>
     */
    protected function getContainers(): Generator
    {
        yield $this->container;
        yield $this->providers;
        yield from $this->containers;
    }

    /**
     * Add container.
     *
     * @param ContainerInterface ...$container
     */
    public function addContainer(ContainerInterface ...$container): void
    {
        array_push($this->containers, ...$container);
    }

    /**
     * Add inflector.
     *
     * @param Inflector ...$inflector
     */
    public function addInflector(Inflector ...$inflector): void
    {
        array_push($this->inflectors, ...$inflector);
    }

    /**
     * Inflects.
     *
     * @param object $obj
     *
     * @return object
     * @throws Exception\ContainerException
     */
    protected function inflects(object $obj): object
    {
        foreach ($this->inflectors as $inflector) {
            if (!is_a($obj, $inflector->getInterface())) {
                continue;
            }

            $this->instantiator->invokeMethod($obj, $inflector->getMethod(), $inflector->getArguments());
        }

        return $obj;
    }

    /**
     * Add provider.
     *
     * @param ServiceProviderInterface ...$provider
     */
    public function addProvider(ServiceProviderInterface ...$provider): void
    {
        $this->providers->addProvider(...$provider);
    }
}