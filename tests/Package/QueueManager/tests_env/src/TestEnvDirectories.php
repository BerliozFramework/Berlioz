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

namespace Berlioz\Package\QueueManager\TestProject;

use Berlioz\Core\Directories\DefaultDirectories;

class TestEnvDirectories extends DefaultDirectories
{
    protected function getLibraryDirectory(): string
    {
        return realpath(__DIR__ . '/../vendor/berlioz/core');
    }
}