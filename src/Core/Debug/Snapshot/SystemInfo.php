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
 * Class SystemInfo.
 */
class SystemInfo
{
    private string $uname;
    private string $currentUser;
    private ?int $uid;
    private ?int $gid;
    private ?int $pid;
    private ?int $inode;
    private string $tmpDir;

    public function __construct()
    {
        $this->snap();
    }

    /**
     * Snap.
     */
    public function snap(): void
    {
        $this->uname = php_uname();
        $this->currentUser = get_current_user();
        $this->uid = getmyuid() ?: null;
        $this->gid = getmygid() ?: null;
        $this->pid = getmypid() ?: null;
        $this->inode = getmyinode() ?: null;
        $this->tmpDir = sys_get_temp_dir();
    }

    /**
     * Get uname.
     *
     * @return string
     */
    public function getUname(): string
    {
        return $this->uname;
    }

    /**
     * Get current user.
     *
     * @return string
     */
    public function getCurrentUser(): string
    {
        return $this->currentUser;
    }

    /**
     * getUID.
     *
     * @return int|null
     */
    public function getUid(): ?int
    {
        return $this->uid;
    }

    /**
     * Get GID.
     *
     * @return int|null
     */
    public function getGid(): ?int
    {
        return $this->gid;
    }

    /**
     * Get PID.
     *
     * @return int|null
     */
    public function getPid(): ?int
    {
        return $this->pid;
    }

    /**
     * Get inode.
     *
     * @return int|null
     */
    public function getInode(): ?int
    {
        return $this->inode;
    }

    /**
     * Get tmp directory.
     *
     * @return string
     */
    public function getTmpDir(): string
    {
        return $this->tmpDir;
    }
}