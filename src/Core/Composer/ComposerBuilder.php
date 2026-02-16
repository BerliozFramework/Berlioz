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

namespace Berlioz\Core\Composer;

use Berlioz\Core\Exception\ComposerException;
use Berlioz\Core\Filesystem\FilesystemInterface;
use League\Flysystem\FilesystemException;

/**
 * Class ComposerBuilder.
 */
class ComposerBuilder
{
    private Composer $composer;

    public function __construct(protected FilesystemInterface $fs)
    {
        $this->reset();
    }

    /**
     * Reset builder.
     */
    public function reset(): void
    {
        unset($this->composer);
    }

    /**
     * Build.
     *
     * @throws FilesystemException
     * @throws ComposerException
     */
    public function build(): void
    {
        if (false === $this->fs->fileExists($composerFile = 'app://composer.json')) {
            throw new ComposerException('Project "composer.json" file not found');
        }

        if (false === ($composerJson = json_decode($this->fs->read($composerFile), true))) {
            throw new ComposerException('Not valid project "composer.json" file');
        }

        if (false === $this->fs->fileExists($lockFile = 'app://composer.lock')) {
            throw new ComposerException('Project "composer.lock" file not found, execute "composer install" command?');
        }

        if (false === ($composerLock = json_decode($this->fs->read($lockFile), true))) {
            throw new ComposerException('Not valid project "composer.lock" file');
        }

        $packages = [];

        foreach (['packages', 'packages-dev'] as $composerKey) {
            foreach ($composerLock[$composerKey] ?? [] as $packageFromLock) {
                $packageComposerFile =
                    $packageFromLock['name'] . '/' .
                    trim($packageFromLock['target-dir'] ?? '', '\\/') .
                    '/composer.json';

                if (false === $this->fs->fileExists($packageComposerFile = 'vendor://' . $packageComposerFile)) {
                    $packages[] = new Package(
                        name: $packageFromLock['name'],
                        version: $packageFromLock['version'],
                        type: $packageFromLock['type'] ?? 'library',
                        description: $packageFromLock['description'] ?? null,
                    );
                    continue;
                }

                $packageComposer = json_decode($this->fs->read($packageComposerFile), true);

                $packages[] = new Package(
                    name: $packageComposer['name'] ?? $packageFromLock['name'],
                    version: $packageComposer['version'] ?? $packageFromLock['version'] ?? null,
                    type: $packageComposer['type'] ?? $packageFromLock['type'] ?? 'library',
                    description: $packageComposer['description'] ?? $packageFromLock['description'] ?? null,
                    config: $packageComposer['config'] ?? $packageFromLock['config'] ?? [],
                );
            }

            $this->composer = new Composer(
                name: $composerJson['name'],
                version: $composerJson['version'] ?? null,
                packages: $packages,
            );
        }
    }

    /**
     * Get composer.
     *
     * @return Composer
     */
    public function getComposer(): Composer
    {
        return $this->composer;
    }
}