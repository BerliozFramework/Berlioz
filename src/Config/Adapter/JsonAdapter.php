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
use JsonException;

/**
 * Class JsonAdapter.
 */
class JsonAdapter extends AbstractFileAdapter
{
    /**
     * @inheritDoc
     */
    protected function load(string $str, bool $strIsUrl = false): array
    {
        if (true === $strIsUrl) {
            $str = $this->loadUrl($str);
        }

        try {
            return json5_decode($str, true, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ConfigException('Not a valid JSON', 0, $exception);
        }
    }
}