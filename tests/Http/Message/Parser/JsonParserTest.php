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

namespace Berlioz\Http\Message\Tests\Parser;

use Berlioz\Http\Message\Parser\JsonParser;
use Berlioz\Http\Message\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class JsonParserTest extends TestCase
{
    public function testParseMessageBody()
    {
        $body = '{"foo": "bar"}';
        $response = new Response($body, 200, ['Content-Type' => 'application/json']);
        $parsedBody = JsonParser::parseMessageBody($response);

        $this->assertEquals(json_decode($body), $parsedBody);
    }

    public static function provideEmptyBodies(): array
    {
        return [
            'empty' => [''],
            'whitespaces' => [" \n\t "],
        ];
    }

    #[DataProvider('provideEmptyBodies')]
    public function testParseMessageBody_emptyBody(string $body)
    {
        $response = new Response($body, 200, ['Content-Type' => 'application/json']);

        $this->assertNull(JsonParser::parseMessageBody($response));
    }

    public function testParseMessageBodyRejectsInvalidJson()
    {
        $response = new Response('{invalid', 200, ['Content-Type' => 'application/json']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot parse JSON contents');

        JsonParser::parseMessageBody($response);
    }

    public function testParseMessageBodyRejectsTooDeepJson()
    {
        // One level deeper than the allowed maximum.
        $depth = JsonParser::MAX_DEPTH + 1;
        $body = str_repeat('[', $depth) . str_repeat(']', $depth);
        $response = new Response($body, 200, ['Content-Type' => 'application/json']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot parse JSON contents');

        JsonParser::parseMessageBody($response);
    }
}
