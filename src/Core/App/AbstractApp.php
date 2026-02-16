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

namespace Berlioz\Core\App;

use Berlioz\Config\ConfigInterface;
use Berlioz\Config\Exception\ConfigException;
use Berlioz\Core\Asset\Assets;
use Berlioz\Core\Core;
use Berlioz\Core\CoreAwareInterface;
use Berlioz\Core\CoreAwareTrait;
use Berlioz\Core\Debug\DebugHandler;
use Berlioz\ServiceContainer\Exception\ContainerException;
use Closure;

/**
 * Class AbstractApp.
 */
abstract class AbstractApp implements CoreAwareInterface
{
    use CoreAwareTrait;

    /**
     * AbstractApp constructor.
     *
     * @param Core|null $core
     */
    public function __construct(?Core $core = null)
    {
        $this->setCore($core ?? new Core());
        $this->getCore()->getContainer()->add($this, 'app');
        $this->boot();
    }

    /**
     * Application boot.
     */
    abstract protected function boot(): void;

    /**
     * Get service.
     *
     * @param string $id
     *
     * @return mixed
     */
    public function get(string $id): mixed
    {
        return $this->getCore()->getContainer()->get($id);
    }

    /**
     * Call.
     *
     * @param Closure|array|string $subject
     * @param array $arguments
     * @param bool $autoWiring
     *
     * @return mixed
     * @throws ContainerException
     */
    public function call(Closure|array|string $subject, array $arguments = [], bool $autoWiring = true): mixed
    {
        return $this->getCore()->getContainer()->call($subject, $arguments, $autoWiring);
    }

    /**
     * Get assets.
     *
     * @return Assets
     */
    public function getAssets(): Assets
    {
        return $this->get(Assets::class);
    }

    /**
     * Get config.
     *
     * @return ConfigInterface
     */
    public function getConfig(): ConfigInterface
    {
        return $this->getCore()->getConfig();
    }

    /**
     * Get config key.
     *
     * @param string $key
     * @param mixed|null $default
     *
     * @return mixed
     * @throws ConfigException
     */
    public function getConfigKey(string $key, mixed $default = null): mixed
    {
        return $this->getConfig()->get($key, $default);
    }

    /**
     * Get debug.
     *
     * @return DebugHandler
     */
    public function getDebug(): DebugHandler
    {
        return $this->getCore()->getDebug();
    }
}
