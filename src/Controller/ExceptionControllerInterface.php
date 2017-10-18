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

namespace Berlioz\Core\Controller;


/**
 * Interface ExceptionControllerInterface.
 *
 * @package Berlioz\Core\Controller
 * @see     \Berlioz\Core\Controller\ControllerInterface
 */
interface ExceptionControllerInterface extends ControllerInterface
{
    /**
     * Catch exception.
     *
     * @param \Exception $e
     *
     * @return string|\Psr\Http\Message\ResponseInterface
     */
    public function catchException(\Exception $e);
}