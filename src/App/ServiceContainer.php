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

namespace Berlioz\Core\App;


use Berlioz\Core\Exception\InvalidArgumentException;
use Berlioz\Core\Exception\ContainerException;
use Berlioz\Core\Exception\ContainerNotFoundException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerAwareInterface;

class ServiceContainer implements ContainerInterface, AppAwareInterface
{
    use AppAwareTrait;
    // Defaults services
    private static $defaults = ['events'     => '\Berlioz\Core\Services\Events\EventManager',
                                'flashbag'   => '\Berlioz\Core\Services\FlashBag',
                                'logging'    => '\Berlioz\Core\Services\Logger',
                                'routing'    => '\Berlioz\Core\Services\Routing\Router',
                                'templating' => '\Berlioz\Core\Services\Template\DefaultEngine'];
    // Constraints for services
    private static $constraints = ['caching'    => '\Psr\SimpleCache\CacheInterface',
                                   'events'     => '\Psr\EventManager\EventManagerInterface',
                                   'flashbag'   => '\Berlioz\Core\Services\FlashBag',
                                   'logging'    => '\Psr\Log\LoggerInterface',
                                   'routing'    => '\Berlioz\Core\Services\Routing\RouterInterface',
                                   'templating' => '\Berlioz\Core\Services\Template\TemplateInterface'];
    /** @var bool Initialized ? */
    private $initialized;
    /** @var array Services configuration */
    private $servicesConfiguration;
    /** @var array Services objects */
    private $servicesObjects;

    /**
     * ServiceContainer constructor.
     */
    public function __construct()
    {
        $this->initialized = false;
        $this->servicesConfiguration = [];
        $this->servicesObjects = [];
    }

    /**
     * __sleep() magic method.
     */
    public function __sleep(): array
    {
        return ['servicesConfiguration'];
    }

    /**
     * __wakeup() magic method.
     */
    public function __wakeup(): void
    {
        $this->initialized = false;
        $this->servicesObjects = [];
    }

    /**
     * Register services.
     *
     * @return void
     * @throws \Berlioz\Core\Exception\ContainerException if an error occurred
     */
    private function registerServices(): void
    {
        if (!$this->initialized) {
            if ($this->hasApp()) {
                $services = $this->getApp()->getConfig()->get('app.services');

                if (!empty($services) && is_array($services)) {
                    // Parse services in configuration
                    foreach ($services as $name => $service) {
                        if (isset($service['class'])) {
                            if (class_exists($service['class'])) {
                                // Check constraints
                                $this->checkConstraints($name, $service['class']);

                                // Add service configuration
                                $this->servicesConfiguration[$name] = $service;
                            } else {
                                throw new ContainerException(sprintf('Class "%s" doesn\'t exists', $service['class']));
                            }
                        } else {
                            throw new ContainerException(sprintf('Service named "%s" has no class specified in configuration', $name));
                        }
                    }
                }

                // Default services
                foreach (self::$defaults as $name => $class) {
                    if (!$this->has($name)) {
                        $this->servicesConfiguration[$name] = ['class' => $class];
                    }
                }

                $this->initialized = true;
            } else {
                throw new ContainerException('No application set in service container object, unable to initialize services');
            }
        }
    }

    /**
     * Check constraints for a service name.
     *
     * @param string $name  Name of service
     * @param string $class Class name of service
     *
     * @return void
     * @throws \Berlioz\Core\Exception\ContainerException
     */
    private function checkConstraints(string $name, string $class): void
    {
        // Check constraint
        if (isset(self::$constraints[$name])) {
            if (!is_a($class, self::$constraints[$name], true)) {
                throw new ContainerException(sprintf('Service "%s" must implements "%s" class', $name, self::$constraints[$name]));
            }
        }
    }

    /**
     * Set a new service.
     *
     * @param string        $name
     * @param string|object $object
     * @param array         $arguments
     *
     * @return \Berlioz\Core\App\ServiceContainer
     * @throws \Berlioz\Core\Exception\InvalidArgumentException if invalid "object" argument
     */
    public function set(string $name, $object, array $arguments = []): ServiceContainer
    {
        // Register services
        $this->registerServices();

        if (is_object($object)) {
            // Check constraints
            $this->checkConstraints($name, get_class($object));

            // Set
            $this->servicesObjects[$name] = $object;
        } else {
            if (is_string($object) && class_exists($object)) {
                // Check constraints
                $this->checkConstraints($name, $object);

                // Add service configuration
                $this->servicesConfiguration[$name] = ['class'     => $object,
                                                       'arguments' => $arguments];
            } else {
                throw new InvalidArgumentException('Invalid "object" argument, must be an object or a valid class name');
            }
        }

        return $this;
    }

    /**
     * Get a service.
     *
     * @param string $name Name of service
     *
     * @return mixed
     * @throws \Berlioz\Core\Exception\ContainerNotFoundException if service not found
     * @throws \Berlioz\Core\Exception\ContainerException if configuration error
     */
    public function get($name)
    {
        // Register services
        $this->registerServices();

        try {
            // Not already instanced ?
            if (!isset($this->servicesObjects[$name])) {
                // Service exists ?
                if (isset($this->servicesConfiguration[$name]) && !is_null($service = $this->servicesConfiguration[$name])) {
                    if (method_exists($service['class'], '__construct')) // Get arguments in config
                    {
                        if (isset($service['arguments']) && is_array($service['arguments'])) {
                            $arguments = $service['arguments'];
                        } else {
                            $arguments = [];
                        }

                        // Check if Application is needed in first parameter
                        {
                            $reflectionMethod = new \ReflectionMethod($service['class'], '__construct');
                            $reflectionParameters = $reflectionMethod->getParameters();

                            if (count($reflectionParameters) > 0) {
                                $reflectionFirstParameter = $reflectionParameters[0];
                                $reflectionClassFirstParameter = $reflectionFirstParameter->getClass();

                                if ($reflectionClassFirstParameter instanceof \ReflectionClass &&
                                    is_a($reflectionClassFirstParameter->getName(), '\Berlioz\Core\App', true)) {
                                    if ($this->hasApp()) {
                                        // Unshift argument
                                        $arguments = [$reflectionFirstParameter->getName() => $this->getApp()] + $arguments;
                                    } else {
                                        throw new ContainerException('No application set in service container object, unable to instance service');
                                    }
                                }
                            }
                        }

                        $this->servicesObjects[$name] = new $service['class'](...array_values($arguments));
                    } else {
                        $this->servicesObjects[$name] = new $service['class'];
                    }

                    // Instances AppAwareInterface ?
                    if ($this->hasApp() && $this->servicesObjects[$name] instanceof AppAwareInterface) {
                        $this->servicesObjects[$name]->setApp($this->getApp());
                    }

                    // Instances LoggerAwareInterface ?
                    if ($this->has('logging') && $this->servicesObjects[$name] instanceof LoggerAwareInterface) {
                        $this->servicesObjects[$name]->setLogger($this->get('logging'));
                    }
                } else {
                    throw new ContainerNotFoundException(sprintf('Service "%s" doesn\'t exists in configuration.', $name));
                }
            }

            return $this->servicesObjects[$name];
        } catch (\Exception $e) {
            if ($e instanceof ContainerException) {
                throw $e;
            } else {
                throw new ContainerException(sprintf('Error during creation of service class "%s"', $name), 0, $e);
            }
        }
    }

    /**
     * Service exists ?
     *
     * @param string $name Name of service
     *
     * @return bool
     */
    public function has($name): bool
    {
        // Not already instanced ?
        if (!isset($this->servicesObjects[$name])) {
            return isset($this->servicesConfiguration[$name]);
        } else {
            return true;
        }
    }
}