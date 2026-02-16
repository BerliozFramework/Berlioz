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

namespace Berlioz\Cli\Core\Command;

use Berlioz\Cli\Core\Console\Environment;

/**
 * Interface CommandInterface.
 */
interface CommandInterface
{
    /**
     * Get description.
     *
     * @return string|null
     */
    public static function getDescription(): ?string;

    /**
     * Get help.
     *
     * @return string|null
     */
    public static function getHelp(): ?string;

    /**
     * Run command.
     *
     * @param Environment $env
     *
     * @return int
     */
    public function run(Environment $env): int;
}