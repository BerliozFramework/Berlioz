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

namespace Berlioz\Core\TestsEnv;

use Berlioz\Core\Core;
use Berlioz\Core\CoreAwareInterface;
use Berlioz\Core\CoreAwareTrait;

class ServiceQux implements CoreAwareInterface
{
    use CoreAwareTrait;

    public $serviceBar;
    public $increment = 1;

    public function __construct(Core $core, ServiceBar $serviceBar)
    {
        $this->core = $core;
        $this->serviceBar = $serviceBar;
    }

    public function inc(int $increment = 1): void
    {
        $this->increment += $increment;
    }
}