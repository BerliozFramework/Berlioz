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

        [$identifier, $path] = explode('://', $path, 2);

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
        ['path' => $path, 'filesystem' => $filesystem] = $this->determineFilesystemAndPath($location);

        return $filesystem->fileExists($path);
    }

    /**
     * @inheritDoc
     */
    public function directoryExists(string $location): bool
    {
        ['path' => $path, 'filesystem' => $filesystem] = $this->determineFilesystemAndPath($location);

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
        ['path' => $path, 'filesystem' => $filesystem] = $this->determineFilesystemAndPath($location);

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
        ['path' => $path, 'filesystem' => $filesystem] = $this->determineFilesystemAndPath($location);

        $filesystem->write($path, $contents, $config);
    }

    /**
     * @inheritDoc
     */
    public function writeStream(string $location, $contents, array $config = []): void
    {
        ['path' => $path, 'filesystem' => $filesystem] = $this->determineFilesystemAndPath($location);

        $filesystem->writeStream($path, $contents, $config);
    }

    /**
     * @inheritDoc
     */
    public function read(string $location): string
    {
        ['path' => $path, 'filesystem' => $filesystem] = $this->determineFilesystemAndPath($location);

        return $filesystem->read($path);
    }

    /**
     * @inheritDoc
     */
    public function readStream(string $location)
    {
        ['path' => $path, 'filesystem' => $filesystem] = $this->determineFilesystemAndPath($location);

        return $filesystem->readStream($path);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $location): void
    {
        ['path' => $path, 'filesystem' => $filesystem] = $this->determineFilesystemAndPath($location);

        $filesystem->delete($path);
    }

    /**
     * @inheritDoc
     */
    public function deleteDirectory(string $location): void
    {
        ['path' => $path, 'filesystem' => $filesystem] = $this->determineFilesystemAndPath($location);

        $filesystem->deleteDirectory($path);
    }

    /**
     * @inheritDoc
     */
    public function createDirectory(string $location, array $config = []): void
    {
        ['path' => $path, 'filesystem' => $filesystem] = $this->determineFilesystemAndPath($location);

        $filesystem->createDirectory($path, $config);
    }

    /**
     * @inheritDoc
     */
    public function setVisibility(string $path, string $visibility): void
    {
        ['path' => $path, 'filesystem' => $filesystem] = $this->determineFilesystemAndPath($path);

        $filesystem->setVisibility($path, $visibility);
    }

    /**
     * @inheritDoc
     */
    public function visibility(string $path): string
    {
        ['path' => $path, 'filesystem' => $filesystem] = $this->determineFilesystemAndPath($path);

        return $filesystem->visibility($path);
    }

    /**
     * @inheritDoc
     */
    public function mimeType(string $path): string
    {
        ['path' => $path, 'filesystem' => $filesystem] = $this->determineFilesystemAndPath($path);

        return $filesystem->mimeType($path);
    }

    /**
     * @inheritDoc
     */
    public function lastModified(string $path): int
    {
        ['path' => $path, 'filesystem' => $filesystem] = $this->determineFilesystemAndPath($path);

        return $filesystem->lastModified($path);
    }

    /**
     * @inheritDoc
     */
    public function fileSize(string $path): int
    {
        ['path' => $path, 'filesystem' => $filesystem] = $this->determineFilesystemAndPath($path);

        return $filesystem->fileSize($path);
    }

    /**
     * @inheritDoc
     */
    public function listContents(string $location, bool $deep = self::LIST_SHALLOW): DirectoryListing
    {
        [
            'identifier' => $identifier,
            'path' => $path,
            'filesystem' => $filesystem
        ] = $this->determineFilesystemAndPath($location);

        return $filesystem
            ->listContents($path, $deep)
            ->map(
                fn(StorageAttributes $attributes) => $attributes->withPath(sprintf('%s://%s', $identifier,
                    $attributes->path()))
            );
    }

    /**
     * @inheritDoc
     */
    public function move(string $source, string $destination, array $config = []): void
    {
        ['path' => $sourcePath, 'filesystem' => $sourceFs] = $this->determineFilesystemAndPath($source);
        ['path' => $destPath, 'filesystem' => $destFs] = $this->determineFilesystemAndPath($destination);

        if ($sourceFs === $destFs) {
            $sourceFs->move($sourcePath, $destPath, $config);
            return;
        }

        $destFs->writeStream($destPath, $sourceFs->readStream($sourcePath), $config);
        $sourceFs->delete($sourcePath);
    }

    /**
     * @inheritDoc
     */
    public function copy(string $source, string $destination, array $config = []): void
    {
        ['path' => $sourcePath, 'filesystem' => $sourceFs] = $this->determineFilesystemAndPath($source);
        ['path' => $destPath, 'filesystem' => $destFs] = $this->determineFilesystemAndPath($destination);

        if ($sourceFs === $destFs) {
            $sourceFs->copy($sourcePath, $destPath, $config);
            return;
        }

        $destFs->writeStream($destPath, $sourceFs->readStream($sourcePath), $config);
    }
}
