<?php
/**
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2017 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\Core\Services;


use Berlioz\Core\App\AppAwareInterface;

interface LoggerInterface extends AppAwareInterface, \Psr\Log\LoggerInterface, \Countable
{
    /**
     * Count number of logs.
     *
     * @return int
     */
    public function count(): int;

    /**
     * Get logs.
     *
     * @param string|null $level Log level
     *
     * @return array
     */
    public function getLogs(string $level = null): array;

    /**
     * Get first time of logs.
     *
     * Return false if no log entries.
     *
     * @return int|false
     */
    public function getFirstTime();
}