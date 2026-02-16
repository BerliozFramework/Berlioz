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

namespace Berlioz\Package\Hector\Tests\Http;

use Berlioz\Http\Core\Exception\Http\InternalServerErrorHttpException;
use Berlioz\Http\Core\Exception\Http\NotFoundHttpException;
use Berlioz\Http\Message\Response;
use Berlioz\Http\Message\ServerRequest;
use Berlioz\Package\Hector\Http\HectorMiddleware;
use Hector\Orm\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class HectorMiddlewareTest extends TestCase
{
    public function testProcess()
    {
        $middleware = new HectorMiddleware();
        $response = $middleware->process(
            new ServerRequest('GET', '/fake'),
            new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return new Response('FOO');
                }
            }
        );

        $this->assertEquals('FOO', $response->getBody()->getContents());
    }

    public function testProcess_entityNotFound()
    {
        $this->expectException(NotFoundHttpException::class);

        $middleware = new HectorMiddleware();
        $middleware->process(
            new ServerRequest('GET', '/fake'),
            new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    throw new NotFoundException();
                }
            }
        );
    }

    public function testProcess_exception()
    {
        $this->expectException(InternalServerErrorHttpException::class);

        $middleware = new HectorMiddleware();
        $middleware->process(
            new ServerRequest('GET', '/fake'),
            new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    throw new InternalServerErrorHttpException();
                }
            }
        );
    }
}
