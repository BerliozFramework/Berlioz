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

use Berlioz\ServiceContainer\Exception\ArgumentException;
use Berlioz\ServiceContainer\Exception\ContainerException;
use Berlioz\ServiceContainer\Exception\InstantiatorException;
use Closure;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

class Instantiator
{
    /**
     * Instantiator constructor.
     *
     * @param ContainerInterface|null $container
     */
    public function __construct(protected ?ContainerInterface $container = null)
    {
    }

    /**
     * Call.
     *
     * @param string|array|Closure $subject
     * @param array $arguments
     * @param bool $autoWiring
     *
     * @return mixed
     * @throws ContainerException
     */
    public function call(string|array|Closure $subject, array $arguments = [], bool $autoWiring = true): mixed
    {
        // Function?
        if ($subject instanceof Closure || (is_string($subject) && function_exists($subject))) {
            return $this->invokeFunction($subject, $arguments, $autoWiring);
        }

        // Method
        if (true === is_array($subject) || true === str_contains($subject, '::')) {
            if (false === is_array($subject)) {
                $subject = explode('::', $subject, 2);
            }

            return $this->invokeMethod($subject[0], $subject[1], $arguments, $autoWiring);
        }

        return $this->newInstanceOf($subject, $arguments, $autoWiring);
    }

    ////////////////////////////
    /// INSTANTIATOR METHODS ///
    ////////////////////////////

    /**
     * Create new instance of a class.
     *
     * @template T
     *
     * @param object|class-string<T> $class Class name or object
     * @param array $arguments Arguments
     * @param bool $autoWiring
     *
     * @return T
     * @throws ContainerException
     */
    public function newInstanceOf(object|string $class, array $arguments = [], bool $autoWiring = true): object
    {
        try {
            // Reflection of class
            $reflectionClass = new ReflectionClass($class);

            if (null !== ($constructor = $reflectionClass->getConstructor())) {
                // Dependency injection?
                if ($autoWiring) {
                    $arguments = $this->getArguments($constructor, $arguments);
                }

                return $reflectionClass->newInstanceArgs($arguments);
            }

            return $reflectionClass->newInstanceWithoutConstructor();
        } catch (ReflectionException $exception) {
            throw InstantiatorException::classError($class, $exception);
        }
    }

    /**
     * Invocation of method.
     *
     * @param object|string $class Class or object
     * @param string $method Method name
     * @param array $arguments Arguments
     * @param bool $autoWiring
     *
     * @return mixed
     * @throws ContainerException
     */
    public function invokeMethod(
        object|string $class,
        string $method,
        array $arguments = [],
        bool $autoWiring = true
    ): mixed {
        try {
            // Reflection of method
            $reflectionMethod = new ReflectionMethod($class, $method);

            // Dependency injection?
            if ($autoWiring) {
                $arguments = $this->getArguments($reflectionMethod, $arguments);
            }

            // Static method
            if ($reflectionMethod->isStatic()) {
                return $reflectionMethod->invokeArgs(null, $arguments);
            }

            // Get instance into container
            if (is_string($class)) {
                if (true === $this->container?->has($class)) {
                    $class = $this->container->get($class);
                }
            }

            // Create new instance of class if not object
            if (is_string($class)) {
                $class = $this->newInstanceOf($class);
            }

            return $reflectionMethod->invokeArgs($class, $arguments);
        } catch (ReflectionException $exception) {
            throw InstantiatorException::methodError($class, $method, $exception);
        }
    }

    /**
     * Invocation of function.
     *
     * @param string|Closure $function Function name
     * @param array $arguments Arguments
     * @param bool $autoWiring
     *
     * @return mixed
     * @throws ContainerException
     */
    public function invokeFunction(string|Closure $function, array $arguments = [], bool $autoWiring = true): mixed
    {
        try {
            // Reflection of function
            $reflectionFunction = new ReflectionFunction($function);

            // Dependency injection?
            if ($autoWiring) {
                $arguments = $this->getArguments($reflectionFunction, $arguments);
            }

            return $reflectionFunction->invokeArgs($arguments);
        } catch (ReflectionException $exception) {
            throw InstantiatorException::functionError($function, $exception);
        }
    }

    /**
     * Get arguments to inject.
     *
     * @param ReflectionFunctionAbstract $reflectionFunction
     * @param array $arguments
     *
     * @return array
     * @throws ContainerException
     */
    protected function getArguments(
        ReflectionFunctionAbstract $reflectionFunction,
        array $arguments = []
    ): array {
        $parameters = [];

        foreach ($reflectionFunction->getParameters() as $reflectionParameter) {
            $parameters[] = $parameterName = $reflectionParameter->getName();

            // Argument already given
            if (array_key_exists($parameterName, $arguments)) {
                $argument = &$arguments[$parameterName];

                if (is_string($argument)) {
                    // It's an alias?
                    if (str_starts_with($argument, '@')) {
                        $serviceName = substr($argument, 1);

                        if (false === $this->container?->has($serviceName)) {
                            throw ArgumentException::missingService($serviceName);
                        }

                        $argument = $this->container->get($serviceName);
                    }
                }
                continue;
            }

            // Search with types
            if (true === $reflectionParameter->hasType()) {
                try {
                    foreach ($this->getParameterTypes($reflectionParameter) as $reflectionType) {
                        if (true === $reflectionType->isBuiltin()) {
                            continue;
                        }

                        if (true === $this->container?->has($reflectionType->getName())) {
                            $arguments[$parameterName] = $this->container->get($reflectionType->getName());
                            continue 2;
                        }

                        if (class_exists($reflectionType->getName())) {
                            $reflectionClass = new ReflectionClass($reflectionType->getName());

                            if (false === $reflectionClass->isInstantiable()) {
                                continue 2;
                            }

                            $arguments[$parameterName] = $this->newInstanceOf($reflectionType->getName());
                            continue 2;
                        }
                    }
                } catch (ArgumentException $exception) {
                }
            }

            // Skip if default value available
            if ($reflectionParameter->isDefaultValueAvailable()) {
                continue;
            }

            // Skip if variadic value
            if ($reflectionParameter->isVariadic()) {
                continue;
            }

            // Allows null value?
            if (true === $reflectionParameter->allowsNull()) {
                $arguments[$parameterName] = null;
                continue;
            }

            throw ArgumentException::missingArgument($parameterName, $reflectionFunction, $exception ?? null);
        }

        return array_intersect_key($arguments, array_fill_keys($parameters, null));
    }

    /**
     * Get parameter types.
     *
     * @param ReflectionParameter $parameter
     *
     * @return ReflectionNamedType[]
     */
    protected function getParameterTypes(ReflectionParameter $parameter): array
    {
        $type = $parameter->getType();

        if ($type instanceof ReflectionUnionType) {
            return $type->getTypes();
        }

        if ($type instanceof ReflectionType) {
            return [$type];
        }

        return [];
    }
}