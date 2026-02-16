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

namespace Berlioz\ServiceContainer\Tests;

use Berlioz\ServiceContainer\Container;
use Berlioz\ServiceContainer\Inflector\Inflector;
use Berlioz\ServiceContainer\Instantiator;
use Generator;

class FakeContainer extends Container
{
    public function getDefaultContainer(): Container\DefaultContainer
    {
        return $this->container;
    }

    public function getProviderContainer(): Container\ProviderContainer
    {
        return $this->providers;
    }

    public function getContainers(): Generator
    {
        return parent::getContainers();
    }

    /**
     * @return Inflector[]
     */
    public function getInflectors(): array
    {
        return $this->inflectors;
    }

    /**
     * @return Instantiator
     */
    public function getInstantiator(): Instantiator
    {
        return $this->instantiator;
    }
}