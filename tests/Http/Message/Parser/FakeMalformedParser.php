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

namespace Berlioz\Http\Message\Tests\Parser;

use Berlioz\Http\Message\Exception\MalformedBodyException;
use Berlioz\Http\Message\Parser\ParserInterface;
use Psr\Http\Message\MessageInterface;

class FakeMalformedParser implements ParserInterface
{
    /**
     * @inheritDoc
     */
    public static function parseMessageBody(MessageInterface $message): mixed
    {
        throw new MalformedBodyException('Fake malformed body');
    }
}
