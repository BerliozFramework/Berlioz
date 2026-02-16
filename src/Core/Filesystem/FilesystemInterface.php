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

namespace Berlioz\Core\Filesystem;

use League\Flysystem\FilesystemOperator;

interface FilesystemInterface extends FilesystemOperator
{
    /**
     * Has identifier?
     *
     * @param string $identifier
     *
     * @return bool
     */
    public function hasIdentifier(string $identifier): bool;

    /**
     * Get filesystem.
     *
     * @param string $identifier
     *
     * @return FilesystemOperator|null
     */
    public function getFilesystem(string $identifier): ?FilesystemOperator;
}