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

namespace Berlioz\ServiceContainer\Container;

use Berlioz\ServiceContainer\Exception\NotFoundException;
use Berlioz\ServiceContainer\Instantiator;
use Berlioz\ServiceContainer\Service\Service;
use Generator;
use Psr\Container\ContainerInterface;

class DefaultContainer implements ContainerInterface
{
    protected Instantiator $instantiator;
    /** @var Service[] */
    protected array $services = [];

    public function __construct(?Instantiator $instantiator = null)
    {
        $this->instantiator = $instantiator ?? new Instantiator($this);
    }

    /**
     * Add service.
     *
     * @param Service ...$service
     */
    public function addService(Service ...$service): void
    {
        array_push($this->services, ...$service);
    }

    /**
     * Get services.
     *
     * @return Generator
     */
    public function getServices(): Generator
    {
        yield from $this->services;
    }

    /**
     * Get service by id.
     *
     * @param string $id
     *
     * @return Service|null
     */
    protected function getService(string $id): ?Service
    {
        // Search alias and class
        foreach ($this->services as $service) {
            if ($id === $service->getAlias()) {
                return $service;
            }

            if ($id === $service->getClass()) {
                return $service;
            }
        }

        // Search provides
        foreach ($this->services as $service) {
            if (in_array($id, $service->getProvides())) {
                return $service;
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function get(string $id): mixed
    {
        $service = $this->getService($id) ?? throw NotFoundException::notFound($id);

        return $service->get($this->instantiator);
    }

    /**
     * @inheritDoc
     */
    public function has(string $id): bool
    {
        return null !== $this->getService($id);
    }
}