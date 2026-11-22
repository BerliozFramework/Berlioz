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

namespace Berlioz\Http\Core\TestProject\Controller;

use Berlioz\Http\Core\Attribute\Route;
use Berlioz\Http\Core\Controller\AbstractController;
use Berlioz\Http\Message\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ControllerOne extends AbstractController
{
    #[Route('/controller1/method1', name: 'c1m1')]
    public function methodOne(): ResponseInterface
    {
        return $this->response('FOO BAR');
    }

    #[Route('/controller2/method2')]
    public function methodTwo(): ResponseInterface
    {
        return $this->response();
    }

    #[Route('/controller1/parsed-body', method: 'POST')]
    public function parsedBody(ServerRequestInterface $request): mixed
    {
        return $request->getParsedBody();
    }

    #[Route('/controller1/upstream-body')]
    public function upstreamBody(): mixed
    {
        $upstream = new Response('{"items":[', 200, ['Content-Type' => 'application/json']);

        return ['data' => $upstream->getParsedBody()];
    }
}