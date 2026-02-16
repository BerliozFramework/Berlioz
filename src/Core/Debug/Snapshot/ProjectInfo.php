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

namespace Berlioz\Core\Debug\Snapshot;

use Berlioz\Core\Composer\Composer;

/**
 * Class ProjectInfo.
 */
class ProjectInfo
{
    private array $declaredClasses;
    private array $includedFiles;

    public function __construct(private Composer $composer)
    {
        $this->snap();
    }

    /**
     * Snap.
     */
    public function snap(): void
    {
        $this->declaredClasses = get_declared_classes();
        $this->includedFiles = get_included_files();
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

    /**
     * Get declared classes.
     *
     * @return array
     */
    public function getDeclaredClasses(): array
    {
        return $this->declaredClasses;
    }

    /**
     * Get included files.
     *
     * @return array
     */
    public function getIncludedFiles(): array
    {
        return $this->includedFiles;
    }
}