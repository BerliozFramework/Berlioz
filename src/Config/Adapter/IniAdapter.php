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

namespace Berlioz\Config\Adapter;

use Berlioz\Config\Exception\ConfigException;

/**
 * Class IniAdapter.
 */
class IniAdapter extends AbstractFileAdapter
{
    /**
     * @inheritDoc
     */
    protected function load(string $str, bool $strIsUrl = false): array
    {
        if (true === $strIsUrl) {
            return @parse_ini_file($str, true) ?: throw new ConfigException('Not a valid INI file');
        }

        return @parse_ini_string($str, true) ?: throw new ConfigException('Not a valid INI contents');
    }
}