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
 * Class PhpError.
 */
class PhpError
{
    public function __construct(
        private int $errno,
        private string $message,
        private ?string $file = null,
        private ?int $line = null
    ) {
    }

    /**
     * Get errno.
     *
     * @return int
     */
    public function getErrno(): int
    {
        return $this->errno;
    }

    /**
     * Get message.
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Get file.
     *
     * @return string|null
     */
    public function getFile(): ?string
    {
        return $this->file;
    }

    /**
     * Get line.
     *
     * @return int|null
     */
    public function getLine(): ?int
    {
        return $this->line;
    }
}