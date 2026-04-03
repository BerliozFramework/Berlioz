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

namespace Berlioz\Cli\Core\Command\Berlioz;

use Berlioz\Cli\Core\Command\AbstractCommand;
use Berlioz\Cli\Core\Command\Argument;
use Berlioz\Cli\Core\Console\Environment;
use Berlioz\Cli\Core\Exception\InvalidArgumentException;
use League\Flysystem\FilesystemException;
use League\Flysystem\StorageAttributes;

/**
 * Class CacheClearCommand.
 */
#[Argument(name: 'all', longPrefix: 'all', description: 'All caches directories', noValue: true, castTo: 'bool')]
#[Argument(name: 'directory', description: 'Directories name', castTo: 'string')]
class CacheClearCommand extends AbstractCommand
{
    public function __construct()
    {
    }

    /**
     * @inheritDoc
     */
    public static function getDescription(): ?string
    {
        return 'Clear cache of Berlioz Framework';
    }

    /**
     * Clear cache.
     *
     * @param bool $directories
     *
     * @return bool
     * @throws FilesystemException
     */
    public function clearCache(bool|array $directories = false): bool
    {
        $result = $this->getApp()->getCore()->getCache()->clear();

        if (false === $directories) {
            return $result;
        }

        $contents = $this->getApp()->getCore()->getFilesystem()->listContents('cache://');

        /** @var StorageAttributes $item */
        foreach ($contents as $item) {
            $basename = basename($item->path());

            // Ignore hidden items only when clearing all directories
            if (true === $directories && str_starts_with($basename, '.')) {
                continue;
            }

            if (true === is_array($directories) && false === in_array($basename, $directories)) {
                continue;
            }

            // Directory
            if ($item->isDir()) {
                $this->getApp()->getCore()->getFilesystem()->deleteDirectory($item->path());
                continue;
            }

            $this->getApp()->getCore()->getFilesystem()->delete($item->path());
        }

        return $result;
    }

    /**
     * @inheritDoc
     * @throws FilesystemException
     * @throws InvalidArgumentException
     */
    public function run(Environment $env): int
    {
        $env->console()->inline('Cache clear... ');
        $env->console()->spinner();

        if (true === $this->clearCache(
                $env->getArgumentMultiple('directory') ?:
                    $env->getArgument('all')
            )) {
            $env->console()->green('done!');
            return 0;
        }

        $env->console()->red('failed!');
        return 1;
    }
}
