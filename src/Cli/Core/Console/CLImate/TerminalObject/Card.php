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

declare(strict_types=1);

namespace Berlioz\Cli\Core\Console\CLImate\TerminalObject;

use League\CLImate\TerminalObject\Basic\BasicTerminalObject;
use League\CLImate\TerminalObject\Helper\StringLength;

/**
 * Class Card.
 */
class Card extends BasicTerminalObject
{
    use StringLength;

    protected int $xPadding = 2;
    protected int $yPadding = 1;

    public function __construct(protected string $str)
    {
    }

    public function result()
    {
        $width = $this->util->width() - 1;
        $strMaxWidth = $width - (2 * $this->xPadding);
        $str = preg_split('/\r\n|\r|\n/', $this->str);
        array_walk($str, fn(&$line) => $line = mb_str_split($line, $strMaxWidth));
        $str = array_merge(...$str);

        $yPaddingArray = array_fill(0, $this->yPadding, str_repeat(' ', $width));

        array_walk(
            $str,
            fn(&$line) => $line =
                str_repeat(' ', $this->xPadding) .
                str_pad($line, $strMaxWidth) .
                str_repeat(' ', $this->xPadding)
        );
        array_unshift($str, ...$yPaddingArray);
        array_push($str, ...$yPaddingArray);

        return $str;
    }
}