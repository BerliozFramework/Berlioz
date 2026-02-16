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

namespace Berlioz\ServiceContainer\Service;

use Berlioz\ServiceContainer\Exception\ContainerException;
use Berlioz\ServiceContainer\Instantiator;

class Service
{
    protected string $class;
    protected bool $nullable = false;
    protected bool $shared = true;
    protected array $provides = [];
    protected mixed $factory = null;
    protected array $arguments = [];
    protected array $calls = [];
    protected ?object $object = null;
    protected bool $retrieved = false;
    protected bool $initialization = false;

    /**
     * Service constructor.
     *
     * @param string|object $class
     * @param null|string $alias
     * @param callable|string|null $factory
     * @param CacheStrategy|null $cacheStrategy
     */
    public function __construct(
        string|object $class,
        protected ?string $alias = null,
        callable|string|null $factory = null,
        protected ?CacheStrategy $cacheStrategy = null,
    ) {
        // Get class name of object
        if (is_object($class)) {
            $this->object = $class;
            $class = get_class($class);
            $this->retrieved = true;
            $this->initialization = true;
        }

        $this->class = $class;
        $this->factory = $factory;
    }

    /**
     * PHP serialize method.
     *
     * @return array
     */
    public function __serialize(): array
    {
        return [
            'class' => $this->class,
            'nullable' => $this->nullable,
            'shared' => $this->shared,
            'factory' => $this->factory,
            'alias' => $this->alias,
            'arguments' => $this->arguments,
            'calls' => $this->calls
        ];
    }

    /**
     * PHP unserialize method.
     *
     * @param array $data
     *
     * @throws ContainerException
     */
    public function __unserialize(array $data): void
    {
        $this->class = $data['class'] ?? throw new ContainerException('Serialization error');
        $this->nullable = $data['nullable'] ?? throw new ContainerException('Serialization error');
        $this->shared = $data['shared'] ?? true;
        $this->factory = $data['factory'] ?? throw new ContainerException('Serialization error');
        $this->alias = $data['alias'] ?? throw new ContainerException('Serialization error');
        $this->arguments = $data['arguments'] ?? throw new ContainerException('Serialization error');
        $this->calls = $data['calls'] ?? throw new ContainerException('Serialization error');
    }

    /**
     * Get class.
     *
     * @return string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * Is nullable?
     *
     * @return bool
     */
    public function isNullable(): bool
    {
        return $this->nullable ?? false;
    }

    /**
     * Set nullable.
     *
     * @param bool $nullable
     *
     * @return static
     */
    public function setNullable(bool $nullable = true): static
    {
        $this->nullable = $nullable;

        return $this;
    }

    /**
     * Is shared?
     *
     * @return bool
     */
    public function isShared(): bool
    {
        return $this->shared ?? false;
    }

    /**
     * Set shared.
     *
     * @param bool $shared
     *
     * @return static
     */
    public function setShared(bool $shared = true): static
    {
        $this->shared = $shared;

        return $this;
    }

    /**
     * Get alias.
     *
     * @return string
     */
    public function getAlias(): string
    {
        return $this->alias ?? $this->class;
    }

    /**
     * Get provides.
     *
     * @return array
     */
    public function getProvides(): array
    {
        return $this->provides;
    }

    /**
     * Add provide.
     *
     * @param string ...$provide
     *
     * @return $this
     */
    public function addProvide(string ...$provide): static
    {
        array_push($this->provides, ...$provide);

        return $this;
    }

    /**
     * Get arguments.
     *
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments ?? [];
    }

    /**
     * Add argument.
     *
     * @param string $name
     * @param mixed $value
     *
     * @return static
     */
    public function addArgument(string $name, mixed $value): static
    {
        $this->arguments[$name] = $value;

        return $this;
    }

    /**
     * Add arguments.
     *
     * @param array $arguments
     *
     * @return static
     */
    public function addArguments(array $arguments): static
    {
        $this->arguments = array_replace($this->arguments, $arguments);

        return $this;
    }

    /**
     * Add call.
     *
     * @param string $method
     * @param array $arguments
     *
     * @return static
     */
    public function addCall(string $method, array $arguments = []): static
    {
        $this->calls[] = [$method, $arguments];

        return $this;
    }

    /**
     * Add calls.
     *
     * @param array $calls
     *
     * @return static
     */
    public function addCalls(array $calls): static
    {
        foreach ($calls as $method => $arguments) {
            $this->addCall($method, $arguments);
        }

        return $this;
    }

    /**
     * Get factory.
     *
     * @return mixed
     */
    public function getFactory(): mixed
    {
        return $this->factory;
    }

    /**
     * Set factory
     *
     * @param callable|string|null $factory
     *
     * @return static
     */
    public function setFactory(callable|string|null $factory): static
    {
        $this->factory = $factory;

        return $this;
    }

    /**
     * Get service.
     *
     * @param Instantiator $instantiator
     *
     * @return object|null
     * @throws ContainerException
     */
    public function get(Instantiator $instantiator): ?object
    {
        // Already retrieved?
        if (true === $this->retrieved) {
            return $this->object;
        }

        // Already in initialization?
        if ($this->initialization) {
            throw new ContainerException(sprintf('Recursive initialization of service "%s"', $this->class));
        }
        $this->initialization = true;

        // Get from cache only for shared services
        if (true === $this->shared) {
            if (true === $this->cacheStrategy?->has($this)) {
                $this->object = $this->cacheStrategy?->get($this);
                $this->retrieved = true;
                $this->calls($this->object, $instantiator);
                $this->initialization = false;

                return $this->object;
            }
        }

        // Factory?
        if (null !== $this->factory) {
            $result = $this->factory($instantiator);

            // NULL result of factory
            if (null !== $result) {
                if (!is_object($result) || !$result instanceof $this->class) {
                    throw ContainerException::exceptedFactory($this, $result);
                }
            }
        } else {
            $result = $instantiator->newInstanceOf($this->class, $this->getArguments());
        }

        true === $this->shared && $this->object = $result;
        $this->assertNullable($result);
        $this->retrieved = $this->shared;
        $this->calls($result, $instantiator);
        true === $this->shared && $this->cacheStrategy?->set($this, $result);
        $this->initialization = false;

        return $result;
    }

    /**
     * Assert nullable.
     *
     * @throws ContainerException
     */
    protected function assertNullable(object|null $object): void
    {
        if (true === $this->nullable) {
            return;
        }

        if (null === $object) {
            throw new ContainerException(sprintf('Service "%s" cannot be NULL', $this->getAlias()));
        }
    }

    /**
     * Factory.
     *
     * @param Instantiator $instantiator
     *
     * @return mixed
     * @throws ContainerException
     */
    protected function factory(Instantiator $instantiator): mixed
    {
        return $instantiator->call($this->factory, $this->getArguments());
    }

    /**
     * Calls.
     *
     * @param object|null $object
     * @param Instantiator $instantiator
     *
     * @throws ContainerException
     */
    protected function calls(?object $object, Instantiator $instantiator): void
    {
        if (null === $object) {
            return;
        }

        foreach ($this->calls ?? [] as $call) {
            $instantiator->invokeMethod($this->object, $call[0], $call[1] ?? []);
        }
    }
}