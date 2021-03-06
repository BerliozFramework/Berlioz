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
use Berlioz\Core\Exception\BerliozException;
use Berlioz\Core\Exception\RoutingException;
use Berlioz\Http\Message\Response;
use Berlioz\Http\Message\Stream;
use Psr\Http\Message\ResponseInterface;

/**
 * REST controller class, it's the parent REST controller class.
 *
 * It's needed to inherit all API REST controller to it.
 *
 * @package Berlioz\Core\Controller
 * @see     \Berlioz\Core\Controller\ControllerInterface
 */
abstract class RestController extends Controller
{
    /**
     * Controller constructor.
     *
     * @param \Berlioz\Core\App $app Application
     */
    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    /**
     * Response to the client.
     *
     * @param bool|object|array|\Exception $mixed Data
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Berlioz\Core\Exception\BerliozException If parameter object does'nt implement \JsonSerializable
     *                                                  interface to be converted
     */
    protected function response($mixed): ResponseInterface
    {
        $statusCode = 200;
        $reasonPhrase = '';
        $headers['Content-Type'] = ['application/json'];
        $body = new Stream();

        // Booleans
        if (is_bool($mixed)) {
            if ($mixed == false) {
                $statusCode = 500;
            }
        } else {
            // Array
            if (is_array($mixed)) {
                $body->write(json_encode($mixed));
            } else {
                // Exception
                if ($mixed instanceof \Exception) {
                    if ($mixed instanceof RoutingException) {
                        $statusCode = $mixed->getCode();
                        $reasonPhrase = $mixed->getMessage();
                    } else {
                        $statusCode = 500;
                    }

                    $body->write(json_encode(['errno' => $mixed->getCode(), 'error' => $mixed->getMessage()]));
                } else {
                    // Object
                    if (is_object($mixed)) {
                        if ($mixed instanceof \JsonSerializable) {
                            $body->write(json_encode($mixed));
                        } else {
                            throw new BerliozException('Parameter object must implement \JsonSerializable interface to be converted');
                        }
                    } else {
                        $statusCode = 500;
                    }
                }
            }
        }

        // Response
        return new Response($body, $statusCode, $headers, $reasonPhrase);
    }
}