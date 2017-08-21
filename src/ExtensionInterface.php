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


interface ExtensionInterface
{
    /**
     * Init extension.
     *
     * @param \Berlioz\Core\App $app Application
     *
     * @return void
     */
    public function init(App $app): void;

    /**
     * Extension is initialized ?
     *
     * @return bool
     */
    public function isInitialized(): bool;
}