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

namespace Berlioz\Core\Entity;


/**
 * Class Collection.
 *
 * @package Berlioz\Core\Entity
 */
class Collection implements \IteratorAggregate, \ArrayAccess, \Countable, \JsonSerializable
{
    /** @var mixed[] List of elements */
    protected $list;
    /** @var string[] Types of accepted objects in the list */
    private $entityClasses;

    /**
     * Collection constructor.
     *
     * @param string|string[] $entityClasses List of class accepted in collection
     */
    public function __construct($entityClasses = null)
    {
        $this->list = [];
        if (!empty($entityClass)) {
            $this->entityClasses = (array) $entityClasses;
        }
    }

    /**
     * Collection destructor.
     */
    public function __destruct()
    {
    }

    /**
     * __clone PHP magic method.
     */
    public function __clone()
    {
        foreach ($this->list as $key => $value) {
            if (is_object($value)) {
                $this->list[$key] = clone $value;
            }
        }
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return mixed
     */
    public function jsonSerialize()
    {
        $data = [];

        foreach ($this as $element) {
            if ($element instanceof \JsonSerializable) {
                $data[] = $element->jsonSerialize();
            }
        }

        return $data;
    }

    /**
     * Create new iterator.
     *
     * @return \ArrayIterator
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->list);
    }

    /**
     * Set the internal pointer of the list to its last element.
     *
     * @return mixed
     */
    public function end()
    {
        return end($this->list);
    }

    /**
     * Move rearward to previous element.
     *
     * @return Entity|false
     */
    public function prev()
    {
        return prev($this->list);
    }

    /**
     * Rewind the Iterator to the first element.
     *
     * @return Entity|false
     * @see Iterator
     */
    public function rewind()
    {
        return reset($this->list);
    }

    /**
     * Count number of elements in the list.
     *
     * @return int
     * @see \Countable
     */
    public function count(): int
    {
        return count($this->list);
    }

    /**
     * Pick random entries out of the list.
     *
     * @return Entity|false
     */
    public function rand()
    {
        return $this->list[array_rand($this->list)];
    }

    /**
     * Reverse order of the list.
     *
     * @return static
     */
    public function invert(): Collection
    {
        $this->list = array_reverse($this->list, true);

        return $this;
    }

    /**
     * Shuffle list and preserve keys.
     *
     * @return static
     */
    public function shuffle(): Collection
    {
        // Get keys and shuffle
        $keys = array_keys($this->list);
        shuffle($keys);

        // Attribute shuffle keys to theirs values
        $newList = [];
        foreach ($keys as $key) {
            $newList[$key] = $this->list[$key];
        }

        // Update list
        $this->list = $newList;

        return $this;
    }

    /**
     * Empty list.
     */
    public function empty(): void
    {
        $this->list = [];
    }

    /**
     * Whether an offset exists.
     *
     * @param mixed $offset An offset to check for
     *
     * @return bool
     * @see \ArrayAccess
     */
    public function offsetExists($offset): bool
    {
        return isset($this->list[$offset]);
    }

    /**
     * Offset to retrieve.
     *
     * @param mixed $offset The offset to retrieve
     *
     * @return Entity|null
     * @see \ArrayAccess
     */
    public function offsetGet($offset)
    {
        return isset($this->list[$offset]) ? $this->list[$offset] : null;
    }

    /**
     * Unset an offset.
     *
     * @param mixed $offset The offset to unset
     *
     * @see \ArrayAccess
     */
    public function offsetUnset($offset): void
    {
        unset($this->list[$offset]);
    }

    /**
     * Assign a value to the specified offset.
     *
     * @param mixed $offset The offset to assign the value to
     * @param mixed $value  The value to set
     *
     * @see \ArrayAccess
     * @throws \InvalidArgumentException if entity isn't accepted
     */
    public function offsetSet($offset, $value): void
    {
        if ($this->isValidEntity($value)) {
            if (is_null($offset) || mb_strlen($offset) == 0) {
                $this->list[] = $value;
            } else {
                $this->list[$offset] = $value;
            }
        } else {
            throw new \InvalidArgumentException(sprintf('This collection does\'t accept this entity "%s"', gettype($value)));
        }
    }

    /**
     * Get keys of list.
     *
     * @return mixed[]
     */
    public function keys(): array
    {
        return array_keys($this->list);
    }

    /**
     * Get values of list.
     *
     * @return Entity[]
     */
    public function values(): array
    {
        return array_values($this->list);
    }

    /**
     * Find an element.
     *
     * @param mixed   $value           Value to found
     * @param string  $property        Property to check
     * @param bool    $strict          Strict result
     * @param mixed[] $callableOptions Options for callable function
     *
     * @return Entity[]
     */
    public function find($value, $property = null, $strict = false, $callableOptions = []): array
    {
        $found = [];

        foreach ($this as $key => $obj) {
            if (!is_null($property)) {
                if (is_callable([$obj, $property])) {
                    if ((true === $strict && call_user_func([$obj, $property], ...$callableOptions) === $value)
                        || (false === $strict && call_user_func([$obj, $property], ...$callableOptions) == $value)
                    ) {
                        $found[] = $obj;
                    }
                } else {
                    $exists = false;
                    $value = b_property_get($obj, $property, $exists);
    
                    if ($exists === true) {
                        if ((true === $strict && $value === $value)
                            || (false === $strict && $value == $value)
                        ) {
                            $found[] = $obj;
                        }
                    }
                }
            } else {
                if ((true === $strict && $key === $value)
                    || (false === $strict && $key == $value)
                ) {
                    $found[] = $obj;
                }
            }
        }

        return $found;
    }

    /**
     * Get accepted entities classes, null if all.
     *
     * @return array|null
     */
    public function getAcceptedEntities(): ?array
    {
        return $this->entityClasses;
    }

    /**
     * Check if element is valid for the list.
     *
     * @param mixed $mixed Element to check
     *
     * @return bool
     */
    public function isValidEntity($mixed): bool
    {
        if (empty($this->entityClasses)) {
            return true;
        } else {
            if (is_object($mixed)) {
                foreach ($this->entityClasses as $entityClass) {
                    if (is_a($mixed, $entityClass, true)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Merge another Collection with this.
     *
     * @param \Berlioz\Core\Entity\Collection $collection Collection to merge
     *
     * @return static
     * @throws \InvalidArgumentException if not the same Collection class
     */
    public function mergeWith(Collection $collection): Collection
    {
        $calledClass = get_called_class();

        if ($collection instanceof $calledClass) {
            foreach ($collection as $key => $object) {
                $this[$key] = $object;
            }
        } else {
            throw new \InvalidArgumentException(sprintf('%s::mergeWith() method require an same type of Collection.', get_class($this)));
        }

        return $this;
    }
}
