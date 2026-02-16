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
use League\Flysystem\UnableToResolveFilesystemMount;

class Filesystem extends AbstractFilesystem
{
    protected array $filesystems = [];

    public function __construct(FilesystemInterface ...$filesystem)
    {
        $this->filesystems = $filesystem;
    }

    /**
     * @inheritDoc
     */
    public function hasIdentifier(string $identifier): bool
    {
        /** @var FilesystemInterface $filesystem */
        foreach ($this->filesystems as $filesystem) {
            if ($filesystem->hasIdentifier($identifier)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function getFilesystem(string $identifier): FilesystemOperator
    {
        /** @var FilesystemInterface $filesystem */
        foreach ($this->filesystems as $filesystem) {
            if ($filesystem->hasIdentifier($identifier)) {
                return $filesystem->getFilesystem($identifier);
            }
        }

        throw UnableToResolveFilesystemMount::becauseTheMountWasNotRegistered($identifier);
    }
}