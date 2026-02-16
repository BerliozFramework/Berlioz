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

use League\Flysystem\DirectoryListing;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToResolveFilesystemMount;
use RuntimeException;

/**
 * Class AbstractFilesystem.
 */
abstract class AbstractFilesystem implements FilesystemInterface
{
    /**
     * Determine filesystem and path.
     *
     * @param string $path
     *
     * @return array
     */
    protected function determineFilesystemAndPath(string $path): array
    {
        if (false === str_contains($path, '://')) {
            throw UnableToResolveFilesystemMount::becauseTheSeparatorIsMissing($path);
        }

        list($identifier, $path) = explode('://', $path, 2);

        return [
            'identifier' => $identifier,
            'path' => $path,
            'filesystem' => $this->getFilesystem($identifier),
        ];
    }

    /**
     * @inheritDoc
     */
    public function fileExists(string $location): bool
    {
        list('path' => $path, 'filesystem' => $filesystem) = $this->determineFilesystemAndPath($location);

        return $filesystem->fileExists($path);
    }

    /**
     * @inheritDoc
     */
    public function directoryExists(string $location): bool
    {
        list('path' => $path, 'filesystem' => $filesystem) = $this->determineFilesystemAndPath($location);

        if (false === method_exists($filesystem, 'directoryExists')) {
            throw new RuntimeException('Need library league/flysystem ^3.0');
        }

        return $filesystem->directoryExists($path);
    }

    /**
     * @inheritDoc
     */
    public function has(string $location): bool
    {
        list('path' => $path, 'filesystem' => $filesystem) = $this->determineFilesystemAndPath($location);

        if (false === method_exists($filesystem, 'has')) {
            throw new RuntimeException('Need library league/flysystem ^3.0');
        }

        return $filesystem->has($path);
    }

    /**
     * @inheritDoc
     */
    public function write(string $location, string $contents, array $config = []): void
    {
        list('path' => $path, 'filesystem' => $filesystem) = $this->determineFilesystemAndPath($location);

        $filesystem->write($path, $contents, $config);
    }

    /**
     * @inheritDoc
     */
    public function writeStream(string $location, $contents, array $config = []): void
    {
        list('path' => $path, 'filesystem' => $filesystem) = $this->determineFilesystemAndPath($location);

        $filesystem->writeStream($path, $contents, $config);
    }

    /**
     * @inheritDoc
     */
    public function read(string $location): string
    {
        list('path' => $path, 'filesystem' => $filesystem) = $this->determineFilesystemAndPath($location);

        return $filesystem->read($path);
    }

    /**
     * @inheritDoc
     */
    public function readStream(string $location)
    {
        list('path' => $path, 'filesystem' => $filesystem) = $this->determineFilesystemAndPath($location);

        return $filesystem->readStream($path);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $location): void
    {
        list('path' => $path, 'filesystem' => $filesystem) = $this->determineFilesystemAndPath($location);

        $filesystem->delete($path);
    }

    /**
     * @inheritDoc
     */
    public function deleteDirectory(string $location): void
    {
        list('path' => $path, 'filesystem' => $filesystem) = $this->determineFilesystemAndPath($location);

        $filesystem->deleteDirectory($path);
    }

    /**
     * @inheritDoc
     */
    public function createDirectory(string $location, array $config = []): void
    {
        list('path' => $path, 'filesystem' => $filesystem) = $this->determineFilesystemAndPath($location);

        $filesystem->createDirectory($path, $config);
    }

    /**
     * @inheritDoc
     */
    public function setVisibility(string $path, string $visibility): void
    {
        list('path' => $path, 'filesystem' => $filesystem) = $this->determineFilesystemAndPath($path);

        $filesystem->setVisibility($path, $visibility);
    }

    /**
     * @inheritDoc
     */
    public function visibility(string $path): string
    {
        list('path' => $path, 'filesystem' => $filesystem) = $this->determineFilesystemAndPath($path);

        return $filesystem->visibility($path);
    }

    /**
     * @inheritDoc
     */
    public function mimeType(string $path): string
    {
        list('path' => $path, 'filesystem' => $filesystem) = $this->determineFilesystemAndPath($path);

        return $filesystem->mimeType($path);
    }

    /**
     * @inheritDoc
     */
    public function lastModified(string $path): int
    {
        list('path' => $path, 'filesystem' => $filesystem) = $this->determineFilesystemAndPath($path);

        return $filesystem->lastModified($path);
    }

    /**
     * @inheritDoc
     */
    public function fileSize(string $path): int
    {
        list('path' => $path, 'filesystem' => $filesystem) = $this->determineFilesystemAndPath($path);

        return $filesystem->fileSize($path);
    }

    /**
     * @inheritDoc
     */
    public function listContents(string $location, bool $deep = self::LIST_SHALLOW): DirectoryListing
    {
        list(
            'identifier' => $identifier,
            'path' => $path,
            'filesystem' => $filesystem
            ) = $this->determineFilesystemAndPath($location);

        return $filesystem
            ->listContents($path, $deep)
            ->map(
                function (StorageAttributes $attributes) use ($identifier) {
                    return $attributes->withPath(sprintf('%s://%s', $identifier, $attributes->path()));
                }
            );
    }

    /**
     * @inheritDoc
     */
    public function move(string $source, string $destination, array $config = []): void
    {
        $sourceAdapter = $this->getFilesystem($source);
        $destinationAdapter = $this->getFilesystem($destination);

        if ($sourceAdapter === $destinationAdapter) {
            $sourceAdapter->move($source, $destination, $config);
            return;
        }

        $destinationAdapter->writeStream($destination, $sourceAdapter->readStream($source), $config);
        $sourceAdapter->delete($source);
    }

    /**
     * @inheritDoc
     */
    public function copy(string $source, string $destination, array $config = []): void
    {
        $sourceAdapter = $this->getFilesystem($source);
        $destinationAdapter = $this->getFilesystem($destination);

        if ($sourceAdapter === $destinationAdapter) {
            $sourceAdapter->move($source, $destination, $config);
            return;
        }

        $destinationAdapter->writeStream($destination, $sourceAdapter->readStream($source), $config);
    }
}