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

/**
 * Class AbstractAdapter.
 */
abstract class AbstractAdapter implements AdapterInterface
{
    use AdapterPriorityTrait;

    protected array $configuration;

    public function __construct(int $priority = 0)
    {
        $this->priority = $priority;
    }

    /**
     * @inheritDoc
     */
    public function get(?string $key = null, mixed $default = null): mixed
    {
        return b_array_traverse_get($this->configuration, $key, $default);
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        return b_array_traverse_exists($this->configuration, $key);
    }

    /**
     * @inheritDoc
     */
    public function getArrayCopy(): array
    {
        return $this->configuration;
    }
}