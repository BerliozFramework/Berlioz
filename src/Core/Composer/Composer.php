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

namespace Berlioz\Core\Composer;

use Generator;

/**
 * Class Composer.
 */
class Composer
{
    /**
     * Composer constructor.
     *
     * @param string $name
     * @param string|null $version
     * @param array $packages
     */
    public function __construct(
        protected string $name,
        protected ?string $version = null,
        protected array $packages = []
    ) {
    }

    /**
     * Get name.
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Get version.
     *
     * @return string|null
     */
    public function getVersion(): ?string
    {
        return $this->version;
    }

    /**
     * Add package.
     *
     * @param Package ...$package
     */
    public function addPackage(Package ...$package): void
    {
        array_push($this->packages, ...$package);
    }

    /**
     * Get packages.
     *
     * @param callable|null $filter
     *
     * @return Generator
     */
    public function getPackages(?callable $filter = null): Generator
    {
        if (null === $filter) {
            yield from $this->packages;
            return;
        }

        foreach ($this->packages as $package) {
            if ($filter($package)) {
                yield $package;
            }
        }
    }

    /**
     * Get berlioz packages.
     *
     * @return Generator
     */
    public function getBerliozPackages(): Generator
    {
        yield from $this->getPackages(fn(Package $package) => 'berlioz-package' === $package->getType());
    }
}