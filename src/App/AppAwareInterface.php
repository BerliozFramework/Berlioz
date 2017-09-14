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

interface AppAwareInterface
{
    /**
     * Get application.
     *
     * @return \Berlioz\Core\App|null
     */
    public function getApp(): ?App;

    /**
     * Set application.
     *
     * @param \Berlioz\Core\App $app
     *
     * @return static
     */
    public function setApp(App $app);

    /**
     * Has application ?
     *
     * @return bool
     */
    public function hasApp(): bool;
}