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

use Berlioz\ServiceContainer\Exception\ContainerException;
use Berlioz\ServiceContainer\Exception\NotFoundException;
use Berlioz\ServiceContainer\Instantiator;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use Throwable;

class AutoWiringContainer implements ContainerInterface
{
    protected Instantiator $instantiator;
    protected array $cache = [];
    protected array $instantiation = [];

    public function __construct(?Instantiator $instantiator = null)
    {
        $this->instantiator = $instantiator ?? new Instantiator($this);
    }

    /**
     * @inheritDoc
     */
    public function get(string $id): mixed
    {
        if (!class_exists($id)) {
            throw NotFoundException::classDoesNotExists($id);
        }

        // Exists in cache
        if (array_key_exists($id, $this->cache)) {
            if (false !== ($object = reset($this->cache[$id]))) {
                return $object;
            }
        }

        try {
            if (in_array($id, $this->instantiation)) {
                throw new ContainerException(sprintf('Recursive initialization of service "%s"', $id));
            }

            $this->instantiation[] = $id;
            $object = $this->instantiator->newInstanceOf($id);

            if (false !== ($implements = class_implements($object))) {
                array_unshift($implements, get_class($object));
                array_walk($implements, fn(string $implement) => $this->cache[$implement][] = $object);
            }

            unset($this->instantiation[array_search($id, $this->instantiation)]);

            return $object;
        } catch (ContainerException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ContainerException::instantiation($id, $exception);
        }
    }

    /**
     * @inheritDoc
     */
    public function has(string $id): bool
    {
        if (array_key_exists($id, $this->cache)) {
            return true;
        }


        if (false === class_exists($id)) {
            return false;
        }

        $reflectionClass = new ReflectionClass($id);
        return $reflectionClass->isInstantiable();
    }
}