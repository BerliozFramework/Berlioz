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

use Berlioz\Core\Debug\Snapshot\PhpError;
use Berlioz\Core\Debug\Snapshot\PhpErrorSet;

/**
 * Class PhpErrorHandler.
 */
class PhpErrorHandler
{
    private array $errors = [];

    public function __construct()
    {
    }

    /**
     * PHP error handler function
     *
     * @param int $errno The level of the error raised
     * @param string $message The error message
     * @param string|null $file The filename that the error was raised in
     * @param int|null $line The line number the error was raised at
     *
     * @return true
     */
    public function handler(int $errno, string $message, ?string $file = null, ?int $line = null): bool
    {
        $this->errors[] = new PhpError(errno: $errno, message: $message, file: $file, line: $line);

        return true;
    }

    /**
     * Handle PHP errors.
     */
    public function handle(): void
    {
        set_error_handler([$this, 'handler']);
    }

    /**
     * Get errors.
     *
     * @return PhpErrorSet
     */
    public function getErrors(): PhpErrorSet
    {
        return new PhpErrorSet(...$this->errors);
    }
}