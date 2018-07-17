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


use Berlioz\Core\App\Profile;
use Berlioz\Core\Exception\BerliozException;
use Berlioz\Core\Exception\RuntimeException;
use Berlioz\ServiceContainer\ServiceContainer;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * App class manage global application with controllers.
 *
 * This class manage controllers and routes.
 * All controllers of the application with routes must be included
 * in this class to work fine.
 *
 * @package Berlioz
 */
class App
{
    /** Key name used in system cache to store routing information */
    const CACHE_KEY_ROUTING = '_BERLIOZ_ROUTE_SET';
    /** Key name used in system cache to store debug profiles */
    const CACHE_KEY_DEBUG = '_BERLIOZ_DEBUG';
    /** @var \Berlioz\Core\ConfigInterface Configuration */
    private $config;
    /** @var \Berlioz\ServiceContainer\ServiceContainer Service container */
    private $services;
    /** @var \Berlioz\Core\App\Profile Profile */
    private $profile;

    /**
     * App constructor.
     *
     * @param \Berlioz\Core\ConfigInterface $config Configuration of application
     */
    public function __construct(ConfigInterface $config)
    {
        $this->setConfig($config);
    }

    /**
     * App destructor.
     */
    public function __destruct()
    {
    }

    /**
     * __sleep() PHP magic method.
     *
     * @throws \Berlioz\Core\Exception\BerliozException
     */
    public function __sleep(): array
    {
        throw new BerliozException(sprintf('"%s" can not be serialized', static::class));
    }

    /**
     * __wakeup() PHP magic method.
     *
     * @throws \Berlioz\Core\Exception\BerliozException
     */
    public function __wakeup(): void
    {
        throw new BerliozException(sprintf('"%s" can not be serialized', static::class));
    }

    /**
     * Get configuration.
     *
     * @return \Berlioz\Core\ConfigInterface
     * @throws \Berlioz\Core\Exception\BerliozException if no configuration initialized
     */
    public function getConfig(): ConfigInterface
    {
        if (is_null($this->config)) {
            throw new BerliozException('No configuration initialized');
        }

        return $this->config;
    }

    /**
     * Set configuration.
     *
     * @param \Berlioz\Core\ConfigInterface $config
     *
     * @return static
     */
    public function setConfig(ConfigInterface $config): App
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Get service container.
     *
     * @return \Berlioz\ServiceContainer\ServiceContainer
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Berlioz\Core\Exception\BerliozException
     */
    public function getServices(): ServiceContainer
    {
        if (is_null($this->services)) {
            $this->services = new ServiceContainer($this->getConfig()->get('app.services'));
            $this->services->registerServices(['events'     => ['class' => '\Berlioz\Core\Services\Events\EventManager'],
                                               'flashbag'   => ['class' => '\Berlioz\Core\Services\FlashBag'],
                                               'logging'    => ['class' => '\Berlioz\Core\Services\Logger'],
                                               'routing'    => ['class' => '\Berlioz\Core\Services\Routing\Router'],
                                               'templating' => ['class' => '\Berlioz\Core\Services\Template\DefaultEngine']]);
            $this->services->setConstraints(['caching'    => '\Psr\SimpleCache\CacheInterface',
                                             'events'     => '\Psr\EventManager\EventManagerInterface',
                                             'flashbag'   => '\Berlioz\Core\Services\FlashBag',
                                             'logging'    => '\Psr\Log\LoggerInterface',
                                             'routing'    => '\Berlioz\Core\Services\Routing\RouterInterface',
                                             'templating' => '\Berlioz\Core\Services\Template\TemplateInterface']);
            $this->services->register('app', $this);
        }

        return $this->services;
    }

    /**
     * Set service container.
     *
     * @param \Psr\Container\ContainerInterface $services
     *
     * @return static
     */
    public function setServices(ContainerInterface $services): App
    {
        $this->services = $services;

        return $this;
    }

    /**
     * Get service.
     *
     * @param string $name Name of service
     *
     * @return mixed
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Berlioz\Core\Exception\BerliozException
     */
    public function getService(string $name)
    {
        return $this->getServices()->get($name);
    }

    /**
     * Has service ?
     *
     * @param string $name Name of service
     *
     * @return bool
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Berlioz\Core\Exception\BerliozException
     */
    public function hasService(string $name): bool
    {
        return $this->getServices()->has($name);
    }

    /**
     * Get profile.
     *
     * @return \Berlioz\Core\App\Profile
     */
    public function getProfile(): Profile
    {
        if (is_null($this->profile)) {
            $this->profile = new Profile($this);
        }

        return $this->profile;
    }

    /**
     * Add extension.
     *
     * @param \Berlioz\Core\ExtensionInterface $extension
     *
     * @return \Berlioz\Core\App
     * @throws \Berlioz\Core\Exception\RuntimeException If unable to load extension
     */
    public function addExtension(ExtensionInterface $extension): App
    {
        try {
            if (!$extension->isInitialized()) {
                $extension->init($this);
            }
        } catch (\Exception $e) {
            throw new RuntimeException(sprintf('Unable to load extension "%s"', get_class($extension)));
        }

        return $this;
    }

    /**
     * Register routes and routing exceptions.
     *
     * Controllers and functions should be declared in this method for the good work of cache system.
     *
     * @return void
     */
    protected function register(): void
    {
    }

    /**
     * Handle.
     *
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Berlioz\Core\Exception\BerliozException
     */
    public function handle(): void
    {
        // Log
        $this->getService('logging')->debug(sprintf('%s / Initialization', __METHOD__));

        // Event
        $this->getService('events')->trigger('_berlioz.core.app.handle.before', $this);

        // Get router
        /** @var \Berlioz\Core\Services\Routing\RouterInterface $router */
        $router = $this->getService('routing');

        // Get route set from cache
        if ($this->getConfig()->hasCacheEnabled() && $this->hasService('caching') && $this->getService('caching')->has(self::CACHE_KEY_ROUTING)) {
            /** @var \Berlioz\Core\Services\Routing\RouteSetInterface $routeSet */
            $routeSet = $this->getService('caching')->get(self::CACHE_KEY_ROUTING);

            // Define route set from cache
            $router->setRouteSet($routeSet);

            // Log
            $this->getService('logging')->debug(sprintf('%s / Router gotten from cache', __METHOD__));
        } else {
            // Register routes ans exceptions
            $this->register();

            // Log
            $this->getService('logging')->debug(sprintf('%s / Routes and exceptions declared', __METHOD__));

            if ($this->getConfig()->hasCacheEnabled() && $this->hasService('caching')) {
                $this->getService('caching')->set(self::CACHE_KEY_ROUTING, $router->getRouteSet());

                // Log
                $this->getService('logging')->debug(sprintf('%s / Router saved in cache', __METHOD__));
            }
        }

        // Handle router
        $response = $router->handle();

        // Debug
        $this->getService('logging')->debug(sprintf('%s / Done', __METHOD__));

        if ($response instanceof ResponseInterface) {
            // Headers
            if (!headers_sent()) {
                // Remove headers and add main header
                header_remove();
                header('HTTP/' . $response->getProtocolVersion() . ' ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase(), false);

                // Headers
                foreach ($response->getHeaders() as $name => $values) {
                    $replace = true;
                    foreach ($values as $value) {
                        header(sprintf('%s: %s', $name, $value), $replace);
                        $replace = false;
                    }
                }
            }

            // Content
            print $response->getBody();
        } else {
            print $response;
        }

        // Log
        $this->getService('logging')->debug(sprintf('%s / Response printed', __METHOD__));

        // Event
        $this->getService('events')->trigger('_berlioz.core.app.handle.after', $this);
    }
}