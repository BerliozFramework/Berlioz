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


use Berlioz\Core\App;
use Berlioz\Core\App\AppAwareInterface;
use Berlioz\Core\Http\ServerRequest;

/**
 * Interface ControllerInterface.
 *
 * @package Berlioz\Core\Controller
 */
interface ControllerInterface extends AppAwareInterface
{
    /**
     * Controller constructor.
     *
     * @param \Berlioz\Core\App $app Application
     */
    public function __construct(App $app);

    /**
     * Magic Berlioz method, called to control access.
     *
     * Called after creation of instance and before _b_init calling.
     * It's used to control access to a controller or application.
     *
     * @param \Berlioz\Core\Http\ServerRequest $request Server request
     *
     * @return \Berlioz\Core\Http\Response|bool
     */
    public function _b_authentication(ServerRequest $request);

    /**
     * Magic Berlioz method, called after initialization and authentication.
     *
     * @param \Berlioz\Core\Http\ServerRequest $request Server request
     *
     * @return void
     */
    public function _b_init(ServerRequest $request): void;
}