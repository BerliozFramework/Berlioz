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

namespace Berlioz\Core\Debug;

use Berlioz\Core\Exception\BerliozException;
use Berlioz\Core\Filesystem\FilesystemInterface;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemException;
use League\Flysystem\StorageAttributes;

/**
 * Class SnapshotCleaner.
 *
 * Garbage collector for debug snapshot files stored on the filesystem.
 */
class SnapshotCleaner
{
    public function __construct(protected FilesystemInterface $filesystem)
    {
    }

    /**
     * List snapshot files, sorted by last modified date (most recent first).
     *
     * @return FileAttributes[]
     * @throws BerliozException
     */
    public function listSnapshots(): array
    {
        try {
            $snapshots = $this->filesystem
                ->listContents('debug://')
                ->filter(fn(StorageAttributes $attr): bool => $attr->isFile())
                ->filter(fn(StorageAttributes $attr): bool => str_ends_with($attr->path(), '.debug'))
                ->toArray();
        } catch (FilesystemException $exception) {
            throw new BerliozException('Filesystem error', 0, $exception);
        }

        usort(
            $snapshots,
            fn(FileAttributes $a, FileAttributes $b): int => ($b->lastModified() ?? 0) <=> ($a->lastModified() ?? 0)
        );

        return $snapshots;
    }

    /**
     * Clean snapshots according to retention policy.
     *
     * Both rules are applied: snapshots older than `$maxAge` days are removed,
     * then only the `$maxFiles` most recent remaining snapshots are kept.
     *
     * @param int|null $maxAge Maximum age in days (null to disable)
     * @param int|null $maxFiles Maximum number of files to keep (null to disable)
     *
     * @return int Number of deleted snapshots
     * @throws BerliozException
     */
    public function clean(?int $maxAge = null, ?int $maxFiles = null): int
    {
        if (null === $maxAge && null === $maxFiles) {
            return 0;
        }

        $snapshots = $this->listSnapshots();
        $toDelete = [];

        // Rule: maximum age
        if (null !== $maxAge && $maxAge >= 0) {
            $limit = time() - ($maxAge * 86400);

            foreach ($snapshots as $key => $snapshot) {
                if (($snapshot->lastModified() ?? 0) < $limit) {
                    $toDelete[$key] = $snapshot;
                }
            }
        }

        // Rule: maximum number of files (applied on remaining snapshots)
        if (null !== $maxFiles && $maxFiles >= 0) {
            $remaining = array_diff_key($snapshots, $toDelete);
            $index = 0;

            foreach ($remaining as $key => $snapshot) {
                if ($index >= $maxFiles) {
                    $toDelete[$key] = $snapshot;
                }
                $index++;
            }
        }

        return $this->deleteFiles($toDelete);
    }

    /**
     * Delete all snapshots.
     *
     * @return int Number of deleted snapshots
     * @throws BerliozException
     */
    public function clear(): int
    {
        return $this->deleteFiles($this->listSnapshots());
    }

    /**
     * Delete a single snapshot by its uniqid.
     *
     * @param string $uniqid
     *
     * @throws BerliozException
     */
    public function delete(string $uniqid): void
    {
        try {
            $this->filesystem->delete(sprintf('debug://%s.debug', basename($uniqid, '.debug')));
        } catch (FilesystemException $exception) {
            throw new BerliozException('Filesystem error', 0, $exception);
        }
    }

    /**
     * Delete given snapshot files.
     *
     * @param FileAttributes[] $snapshots
     *
     * @return int Number of deleted snapshots
     * @throws BerliozException
     */
    private function deleteFiles(array $snapshots): int
    {
        $deleted = 0;

        try {
            foreach ($snapshots as $snapshot) {
                $this->filesystem->delete($snapshot->path());
                $deleted++;
            }
        } catch (FilesystemException $exception) {
            throw new BerliozException('Filesystem error', 0, $exception);
        }

        return $deleted;
    }
}
