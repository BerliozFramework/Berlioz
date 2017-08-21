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


use Berlioz\Core\OptionList;

/**
 * Class Collection.
 *
 * @package Berlioz\Core\Entity
 */
abstract class Collection implements \IteratorAggregate, \ArrayAccess, \Countable, \JsonSerializable
{
    /** @var mixed[] List of elements */
    protected $list;
    /** @var string[] Types of accepted objects in the list */
    private $entityClass;
    /** @var \Berlioz\Core\OptionList Options for list */
    protected $options;
    /** @var int Number of elements in list */
    public $nb;
    /** @var int Total number of elements */
    public $nbTotal;
    /** @var int Number of elements remaining */
    public $nbRemaining;

    /**
     * Collection constructor.
     *
     * @param string|string[] $entityClass List of class accepted in collection
     * @param OptionList|null $options     Options for collection
     */
    public function __construct($entityClass, OptionList $options = null)
    {
        $this->list = [];
        $this->entityClass = (array) $entityClass;

        if (is_null($options)) {
            $this->options = new OptionList;
        } else {
            $this->options = $options;
        }

        $this->nb = 0;
        $this->nbTotal = 0;
        $this->nbRemaining = 0;
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

        $this->options = clone $this->options;
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
    public function end(): mixed
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

        $this->nb = 0;
        $this->nbTotal = 0;
        $this->nbRemaining = 0;
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
     */
    public function offsetSet($offset, $value): void
    {
        if ($this->isValidElement($value)) {
            if (is_null($offset) || mb_strlen($offset) == 0) {
                $this->list[] = $value;
            } else {
                $this->list[$offset] = $value;
            }
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
     * @param  mixed   $value    Value to found
     * @param  string  $property Property to check
     * @param  boolean $strict   Strict result
     *
     * @return Entity[]
     */
    public function find($value, $property = null, $strict = false): array
    {
        $found = [];

        foreach ($this as $key => $obj) {
            if (!is_null($property)) {
                if (isset($obj->$property)) {
                    if ((true === $strict && $obj->$property === $value)
                        || (false === $strict && $obj->$property == $value)
                    ) {
                        $found[] = $obj;
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
     * Get options of Collection.
     *
     * @return \Berlioz\Core\OptionList
     */
    public function getOptions(): OptionList
    {
        if (is_null($this->options)) {
            $this->options = new OptionList;
        }

        return $this->options;
    }

    /**
     * Check if element is valid for the list.
     *
     * @param mixed $mixed Element to check
     *
     * @return bool
     */
    public function isValidElement($mixed): bool
    {
        $bReturn = false;

        if (is_object($mixed)) {
            foreach ($this->entityClass as $entityClass) {
                if (is_a($mixed, $entityClass)) {
                    $bReturn = true;
                }
            }
        }

        return $bReturn;
    }

    /**
     * Merge another Collection with this.
     *
     * @param \Berlioz\Core\Entity\Collection $collection Collection to merge
     *
     * @return static
     */
    public function mergeWith(Collection $collection): Collection
    {
        $calledClass = get_called_class();

        if ($collection instanceof $calledClass) {
            foreach ($collection as $key => $object) {
                $this[$key] = $object;
            }
        } else {
            trigger_error("mergeWith() method require an same type of Collection.", E_USER_ERROR);
        }

        return $this;
    }
}
