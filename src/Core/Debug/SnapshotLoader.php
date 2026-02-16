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

namespace Berlioz\Core\Debug;

use Berlioz\Core\Exception\BerliozException;
use Berlioz\Core\Filesystem\FilesystemInterface;
use League\Flysystem\FilesystemException;

/**
 * Class SnapshotLoader.
 */
class SnapshotLoader
{
    public function __construct(protected FilesystemInterface $filesystem)
    {
    }

    /**
     * Load snapshot.
     *
     * @param string $uniqid
     *
     * @return Snapshot
     * @throws BerliozException
     */
    public function load(string $uniqid): Snapshot
    {
        try {
            if (false === $this->filesystem->fileExists($filename = sprintf('debug://%s.debug', basename($uniqid)))) {
                throw new BerliozException(sprintf('Debug snapshot id "%s" does not exists', basename($uniqid)));
            }

            $snapshot = $this->filesystem->read($filename);
            $snapshot = gzinflate($snapshot);
            $snapshot = unserialize($snapshot);

            if (false === ($snapshot instanceof Snapshot)) {
                throw new BerliozException(sprintf('Invalid snapshot file for id "%s"', basename($uniqid)));
            }

            return $snapshot;
        } catch (FilesystemException $exception) {
            throw new BerliozException('Filesystem error', 0, $exception);
        }
    }

    /**
     * Save snapshot.
     *
     * @param Snapshot $snapshot
     *
     * @throws BerliozException
     */
    public function save(Snapshot $snapshot): void
    {
        try {
            $uniqid = $snapshot->getUniqid();
            $snapshot = serialize($snapshot);
            $snapshot = gzdeflate($snapshot);

            $this->filesystem->write(sprintf('debug://%s.debug', basename($uniqid)), $snapshot);
        } catch (FilesystemException $exception) {
            throw new BerliozException('Filesystem error', 0, $exception);
        }
    }
}