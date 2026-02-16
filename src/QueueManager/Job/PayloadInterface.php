<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2024 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\QueueManager\Job;

use Berlioz\QueueManager\Exception\PayloadException;
use JsonSerializable;

interface PayloadInterface extends JsonSerializable
{
    /**
     * Get array copy.
     *
     * @return array
     */
    public function getArrayCopy(): array;

    /**
     * Get value in payload.
     *
     * @param string $path
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function get(string $path, mixed $default = null): mixed;

    /**
     * Get value in payload or fail.
     *
     * @param string $path
     *
     * @return mixed
     * @throws PayloadException If path does not exist in payload
     */
    public function getOrFail(string $path): mixed;
}
