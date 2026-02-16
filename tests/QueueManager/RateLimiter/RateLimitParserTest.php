<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2025 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\QueueManager\Tests\RateLimiter;

use Berlioz\QueueManager\RateLimiter\RateLimitParser;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RateLimitParserTest extends TestCase
{
    public static function providesLimits(): array
    {
        return [
            [
                'str' => '10/minute',
                'limit' => 10,
                'step' => 60,
            ],
            [
                'str' => '10/min',
                'limit' => 10,
                'step' => 60,
            ],
            [
                'str' => '10/h',
                'limit' => 10,
                'step' => 3600,
            ],
            [
                'str' => '2/5min',
                'limit' => 2,
                'step' => 300,
            ],
            [
                'str' => '1 / h',
                'limit' => 1,
                'step' => 3600,
            ],
            [
                'str' => '1 / 2h',
                'limit' => 1,
                'step' => 7200,
            ],
        ];
    }

    #[DataProvider('providesLimits')]
    public function testParse(string $str, int $limit, int $step): void
    {
        $rateLimit = RateLimitParser::parse($str);

        $this->assertEquals($rateLimit, [$limit, $step]);

    }

    public function testParseInvalidFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid time rate limit "10+min"');

        RateLimitParser::parse('10+min');
    }

    public function testParseInvalidUnit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid time rate limit unit "mns"');

        RateLimitParser::parse('10/mns');
    }
}
