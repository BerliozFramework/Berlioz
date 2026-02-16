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

use Countable;
use Generator;

/**
 * Class PhpErrorSet.
 */
class PhpErrorSet implements Countable
{
    private array $errors;

    public function __construct(PhpError ...$errors)
    {
        $this->errors = $errors;
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->errors);
    }

    /**
     * Get errors.
     *
     * @return Generator
     */
    public function getErrors(): Generator
    {
        yield from $this->errors;
    }
}