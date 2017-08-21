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


use Berlioz\Core\App;

class Profile
{
    /** @var \Berlioz\Core\App Application */
    private $app;
    /** @var string Locale */
    private $locale;
    /** @var \Berlioz\Core\Services\Routing\RouteInterface Current route */
    private $route;

    /**
     * Profile constructor.
     *
     * @param \Berlioz\Core\App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * Get configuration.
     *
     * @return \Berlioz\Core\ConfigInterface
     */
    public function getConfig()
    {
        return $this->app->getConfig();
    }

    /**
     * Get flash bag.
     *
     * @return \Berlioz\Core\Services\FlashBag
     */
    public function getFlashBag()
    {
        return $this->app->getService('flashbag');
    }

    /**
     * Get locale.
     *
     * @return string
     */
    public function getLocale(): string
    {
        if (is_null($this->locale)) {
            $this->locale = \Locale::getDefault();
        }

        return $this->locale;
    }

    /**
     * Set locale.
     *
     * @param string $locale
     */
    public function setLocale(string $locale)
    {
        $this->locale = $locale;
    }

    /**
     * Get current route.
     *
     * @return mixed
     */
    public function getRoute()
    {
        return $this->route;
    }

    /**
     * Set current route.
     *
     * @param mixed $route
     */
    public function setRoute($route)
    {
        $this->route = $route;
    }
}