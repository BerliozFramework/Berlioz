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

use Berlioz\Core\Directories\DirectoriesInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToResolveFilesystemMount;

/**
 * Class BerliozFilesystem.
 */
class BerliozFilesystem extends AbstractFilesystem
{
    protected array $mounted = [];

    public function __construct(protected DirectoriesInterface $directories)
    {
    }

    /**
     * @inheritDoc
     */
    public function hasIdentifier(string $identifier): bool
    {
        return
            array_key_exists($identifier, $this->mounted) ||
            array_key_exists($identifier, $this->directories->getArrayCopy());
    }

    /**
     * @inheritDoc
     */
    public function getFilesystem(string $identifier): ?FilesystemOperator
    {
        if (array_key_exists($identifier, $this->mounted)) {
            return $this->mounted[$identifier];
        }

        $path = $this->directories->getArrayCopy()[$identifier] ??
            throw UnableToResolveFilesystemMount::becauseTheMountWasNotRegistered($identifier);
        $this->mounted[$identifier] = new Filesystem(new LocalFilesystemAdapter($path));

        return $this->mounted[$identifier];
    }
}