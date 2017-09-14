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

namespace Berlioz\Core\Services\Events;


use Berlioz\Core\Exception\RuntimeException;
use Psr\EventManager\EventInterface;

class Event implements EventInterface
{
    /** @var string Name */
    private $name;
    /** @var null|string|object Target/context */
    private $target;
    /** @var array Parameters */
    private $params;
    /** @var bool Propagation stopped ? */
    private $propagationStopped;

    /**
     * Valid event name.
     *
     * @param string $event
     *
     * @return bool
     */
    public static function validEventName(string $event): bool
    {
        return preg_match('/^[\w_]+(?:\.[\w_]+)*$/', $event) == 1;
    }

    /**
     * Event constructor.
     *
     * @param string             $name
     * @param null|string|object $target
     * @param array              $params
     */
    public function __construct(string $name, $target = null, array $params = [])
    {
        $this->setName($name);
        $this->setTarget($target);
        $this->setParams($params);
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * @inheritdoc
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @inheritdoc
     */
    public function getParam($name)
    {
        return $this->params[$name] ?? null;
    }

    /**
     * @inheritdoc
     * @throws \Berlioz\Core\Exception\RuntimeException If event has bad name.
     */
    public function setName($name)
    {
        if (self::validEventName($name)) {
            $this->name = $name;
        } else {
            throw new RuntimeException(sprintf('Event "%s" have a bad name', $name));
        }
    }

    /**
     * @inheritdoc
     * @throws \Berlioz\Core\Exception\RuntimeException If event has bad type.
     */
    public function setTarget($target)
    {
        if (is_null($target) || is_object($target) || is_string($target)) {
            $this->target = $target;
        } else {
            throw new RuntimeException(sprintf('Event "%s" must have type null, string or object', $this->getName()));
        }
    }

    /**
     * @inheritdoc
     */
    public function setParams(array $params)
    {
        $this->params = $params;
    }

    /**
     * Set event parameter.
     *
     * @param string $name
     * @param mixed  $value
     */
    public function setParam(string $name, $value)
    {
        $this->params[$name] = $value;
    }

    /**
     * @inheritdoc
     */
    public function stopPropagation($flag)
    {
        $this->propagationStopped = $flag == true;
    }

    /**
     * @inheritdoc
     */
    public function isPropagationStopped()
    {
        return $this->propagationStopped == true;
    }
}