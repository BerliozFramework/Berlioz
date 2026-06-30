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

use Berlioz\Config\ConfigInterface;
use Berlioz\Core\Exception\BerliozException;
use Berlioz\Core\Filesystem\FilesystemInterface;
use League\Flysystem\FilesystemException;
use Throwable;

/**
 * Class SnapshotLoader.
 */
class SnapshotLoader
{
    public function __construct(
        protected FilesystemInterface $filesystem,
        protected ?ConfigInterface $config = null,
    ) {
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

        $this->collectGarbage();
    }

    /**
     * Run garbage collection on debug snapshots, according to configuration.
     *
     * Triggered probabilistically (gc.probability / gc.divisor) to avoid an I/O
     * cost on every request. Never throws: garbage collection must not break the
     * application.
     */
    protected function collectGarbage(): void
    {
        if (null === $this->config) {
            return;
        }

        try {
            $probability = (int)$this->config->get('berlioz.debug.gc.probability', 0);
            $divisor = (int)$this->config->get('berlioz.debug.gc.divisor', 0);

            if ($probability <= 0 || $divisor <= 0) {
                return;
            }

            if (random_int(1, $divisor) > $probability) {
                return;
            }

            $maxAge = $this->config->get('berlioz.debug.gc.max_age');
            $maxFiles = $this->config->get('berlioz.debug.gc.max_files');

            (new SnapshotCleaner($this->filesystem))->clean(
                null !== $maxAge ? (int)$maxAge : null,
                null !== $maxFiles ? (int)$maxFiles : null,
            );
        } catch (Throwable) {
            trigger_error('Unable to collect debug snapshots garbage', E_USER_WARNING);
        }
    }
}