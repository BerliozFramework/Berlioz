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

namespace Berlioz\Config\Adapter;

use Berlioz\Config\Exception\ConfigException;
use Berlioz\Config\Exception\NotFoundException;

/**
 * Class AbstractFileAdapter.
 */
abstract class AbstractFileAdapter extends AbstractAdapter
{
    /**
     * AbstractAdapter constructor.
     *
     * @param string $str
     * @param bool $strIsUrl
     * @param int $priority
     *
     * @throws ConfigException
     */
    public function __construct(
        string $str,
        bool $strIsUrl = false,
        int $priority = 0,
    ) {
        parent::__construct($priority);

        // Load configuration
        $this->configuration = $this->load($str, $strIsUrl);
    }

    /**
     * Load configuration.
     *
     * @param string $str
     * @param bool $strIsUrl
     *
     * @return array
     * @throws ConfigException
     */
    abstract protected function load(string $str, bool $strIsUrl = false): array;

    /**
     * Load file contents.
     *
     * @param string $filename
     *
     * @return string
     * @throws ConfigException
     */
    protected function loadUrl(string $filename): string
    {
        // Get real path of file
        if (($normalizedFilename = realpath($filename)) === false) {
            throw new NotFoundException(sprintf('File "%s" not found', $filename));
        }

        // Read file
        if (($contents = @file_get_contents($normalizedFilename)) === false) {
            throw new ConfigException(sprintf('Unable to load configuration file "%s"', $normalizedFilename));
        }

        return $contents;
    }
}