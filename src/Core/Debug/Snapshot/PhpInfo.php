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

/**
 * Class PhpInfo.
 */
class PhpInfo
{
    private string $version;
    private string $sapiName;
    private int $memoryLimit;
    private array $extensions;

    public function __construct()
    {
        $this->snap();
    }

    /**
     * Snap.
     */
    public function snap(): void
    {
        $this->version = phpversion();
        $this->sapiName = php_sapi_name();
        $this->memoryLimit = b_size_from_ini(ini_get('memory_limit'));
        $this->extensions = get_loaded_extensions();
    }

    /**
     * Get version.
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Get SAPI name.
     *
     * @return string
     */
    public function getSapiName(): string
    {
        return $this->sapiName;
    }

    /**
     * Get memory limit.
     *
     * @return int
     */
    public function getMemoryLimit(): int
    {
        return $this->memoryLimit;
    }

    /**
     * Get extensions.
     *
     * @return array
     */
    public function getExtensions(): array
    {
        return $this->extensions;
    }
}