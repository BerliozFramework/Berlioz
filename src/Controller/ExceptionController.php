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


use Berlioz\Core\Exception\RoutingException;
use Berlioz\Core\Http\Response;
use Berlioz\Core\Http\Stream;
use Berlioz\Core\Services\Routing\RouterInterface;

class ExceptionController extends Controller implements ExceptionControllerInterface
{
    /**
     * @inheritdoc
     */
    public function catchException(\Exception $e)
    {
        if (!($e instanceof RoutingException)) {
            $e = new RoutingException(RouterInterface::HTTP_STATUS_INTERNAL_SERVER_ERROR, '', $e);
        }

        $rendering = $this->render('@Berlioz-Core/Controller/ExceptionController.twig',
                                   ['exception' => $e]);

        // Make response
        $body = new Stream;
        $body->write($rendering);
        $response = new Response($e->getCode(), [], $body, $e->getMessage());

        return $response;
    }
}