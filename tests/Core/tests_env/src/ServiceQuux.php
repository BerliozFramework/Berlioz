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

class ServiceQuux
{
    public $serviceQux;
    public $factory = false;

    public function __construct(ServiceQux $serviceQux)
    {
        $this->serviceQux = $serviceQux;
    }

    public static function factory(ServiceQux $serviceQux)
    {
        $service = new ServiceQuux($serviceQux);
        $service->factory = true;

        return $service;
    }
}