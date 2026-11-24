<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Http\Client\Tests\Har;

use Berlioz\Http\Client\Har\HarHandler;
use ElGigi\HarParser\Entities\Content;
use ElGigi\HarParser\Entities\Header;
use ElGigi\HarParser\Entities\Response;
use PHPUnit\Framework\TestCase;

class HarHandlerTest extends TestCase
{
    public function testGetHttpResponse_keepsPseudoHeaders(): void
    {
        $harResponse = new Response(
            status: 200,
            statusText: '',
            httpVersion: '2',
            cookies: [],
            headers: [
                new Header(':status', '200'),
                new Header('content-type', 'text/plain'),
            ],
            content: new Content(size: 3, compression: null, mimeType: 'text/plain', text: 'FOO'),
            redirectURL: '',
            headersSize: -1,
            bodySize: 3,
        );

        $response = (new HarHandler())->getHttpResponse($harResponse);

        $this->assertSame(['200'], $response->getHeader(':status'));
        $this->assertSame(['text/plain'], $response->getHeader('Content-Type'));
        $this->assertSame('FOO', (string)$response->getBody());
    }
}
