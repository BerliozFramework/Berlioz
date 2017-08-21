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
use Berlioz\Core\Exception\RuntimeException;


/**
 * Describes a app-aware instance.
 */
trait AppAwareTrait
{
    /** @var \Berlioz\Core\App Application */
    private $app;

    /**
     * Get application.
     *
     * @return \Berlioz\Core\App
     * @throws \Berlioz\Core\Exception\RuntimeException if no app defined
     */
    public function getApp(): App
    {
        if (is_null($this->app)) {
            throw new RuntimeException('No app defined');
        }

        return $this->app;
    }

    /**
     * Set application.
     *
     * @param \Berlioz\Core\App $app
     */
    public function setApp(App $app)
    {
        $this->app = $app;
    }

    /**
     * Has application ?
     *
     * @return bool
     */
    public function hasApp()
    {
        return !is_null($this->app);
    }
}