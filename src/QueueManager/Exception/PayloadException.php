<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2025 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\QueueManager\Exception;

class PayloadException extends QueueManagerException
{
    /**
     * Path not found.
     *
     * @param string $path
     *
     * @return self
     */
    public static function pathNotFound(string $path): self
    {
        return new self(sprintf('Path "%s" not found.', $path));
    }
}
