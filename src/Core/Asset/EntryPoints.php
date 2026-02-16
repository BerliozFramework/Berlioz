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

namespace Berlioz\Core\Asset;

use Berlioz\Core\Exception\AssetException;

/**
 * Class EntryPoints.
 */
class EntryPoints extends JsonAsset
{
    /**
     * EntryPoints constructor.
     *
     * @param string $filename Filename
     * @param string|null $target Target to get entry points
     */
    public function __construct(
        string $filename,
        protected ?string $target = null
    ) {
        parent::__construct($filename);
    }

    /**
     * @inheritDoc
     */
    public function reload(): void
    {
        parent::reload();

        if (!empty($this->target)) {
            if (null === ($assets = b_array_traverse_get($this->assets, $this->target))) {
                throw new AssetException(sprintf('Key "%s" to target entry points is invalid', $this->target));
            }

            $this->assets = (array)$assets;
        }
    }

    /**
     * Get asset for given entry name and file type.
     *
     * @param string|string[] $entry
     * @param string|null $type
     *
     * @return array
     * @throws AssetException
     */
    public function get(string|array $entry, ?string $type = null): array
    {
        $this->loadOnce();

        $assets = [];

        foreach ((array)$entry as $entryName) {
            $tmp = $this->assets[$entryName] ?? [];

            array_walk(
                $tmp,
                function (&$value) {
                    if (!is_array($value)) {
                        $value = [$value];
                    }
                }
            );

            $assets = array_merge_recursive($assets, $tmp);
        }

        array_walk($assets, fn(&$value) => $value = array_unique($value));


        if (null === $type) {
            return $assets;
        }

        return $assets[$type] ?? [];
    }
}