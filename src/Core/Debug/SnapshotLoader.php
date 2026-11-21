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
use JsonException;
use League\Flysystem\FilesystemException;
use Throwable;

/**
 * Class SnapshotLoader.
 */
class SnapshotLoader
{
    public const DEFAULT_PENDING_TTL = 300;

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

            // Snapshot written before removing the marker: there is never a moment without any file
            if ($this->filesystem->fileExists($pendingFilename = $this->getPendingFilename($uniqid))) {
                $this->filesystem->delete($pendingFilename);
            }
        } catch (FilesystemException $exception) {
            throw new BerliozException('Filesystem error', 0, $exception);
        }

        $this->collectGarbage();
    }

    /**
     * Mark snapshot as pending.
     *
     * Written when the response is sent while the snapshot is not yet saved
     * (work still running after the response), so that the debug console
     * knows the snapshot is in progress.
     *
     * @param string $uniqid
     * @param array $data Additional data (e.g. response time)
     *
     * @throws BerliozException
     */
    public function markPending(string $uniqid, array $data = []): void
    {
        $now = microtime(true);

        try {
            $this->filesystem->write(
                $this->getPendingFilename($uniqid),
                json_encode(
                    [
                        ...$data,
                        'uniqid' => basename($uniqid),
                        'time' => $now,
                        'expire' => $now + $this->getPendingTtl(),
                        'pid' => getmypid() ?: null,
                    ],
                    JSON_THROW_ON_ERROR
                )
            );
        } catch (FilesystemException|JsonException $exception) {
            throw new BerliozException('Unable to mark debug snapshot as pending', 0, $exception);
        }
    }

    /**
     * Get pending marker data.
     *
     * @param string $uniqid
     *
     * @return array|null
     */
    public function getPending(string $uniqid): ?array
    {
        try {
            if (false === $this->filesystem->fileExists($filename = $this->getPendingFilename($uniqid))) {
                return null;
            }

            $data = json_decode($this->filesystem->read($filename), true);
        } catch (FilesystemException) {
            // Marker removed between the check and the read (snapshot just saved)
            return null;
        }

        return is_array($data) ? $data : null;
    }

    /**
     * Get status of snapshot.
     *
     * @param string $uniqid
     *
     * @return SnapshotStatus
     * @throws BerliozException
     */
    public function status(string $uniqid): SnapshotStatus
    {
        // Marker read first: the snapshot is written before the marker is removed
        $pending = $this->getPending($uniqid);

        try {
            if (true === $this->filesystem->fileExists(sprintf('debug://%s.debug', basename($uniqid)))) {
                return SnapshotStatus::READY;
            }
        } catch (FilesystemException $exception) {
            throw new BerliozException('Filesystem error', 0, $exception);
        }

        if (null === $pending) {
            return SnapshotStatus::MISSING;
        }

        if ((float)($pending['expire'] ?? 0) < microtime(true)) {
            return SnapshotStatus::ABANDONED;
        }

        return SnapshotStatus::PENDING;
    }

    /**
     * Get TTL of pending marker, in seconds.
     *
     * Configuration "berlioz.debug.pending_ttl", default to "max_execution_time"
     * or DEFAULT_PENDING_TTL if unlimited.
     *
     * @return int
     */
    public function getPendingTtl(): int
    {
        try {
            $ttl = $this->config?->get('berlioz.debug.pending_ttl');
        } catch (Throwable) {
            $ttl = null;
        }

        if (is_numeric($ttl) && (int)$ttl > 0) {
            return (int)$ttl;
        }

        $maxExecutionTime = (int)ini_get('max_execution_time');

        return $maxExecutionTime > 0 ? $maxExecutionTime : self::DEFAULT_PENDING_TTL;
    }

    /**
     * Get pending marker filename.
     *
     * @param string $uniqid
     *
     * @return string
     */
    protected function getPendingFilename(string $uniqid): string
    {
        return sprintf('debug://%s.pending', basename($uniqid));
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