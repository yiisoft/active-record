<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Traits;

use Yiisoft\ActiveRecord\Contracts\ActiveQueryInterface;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\UnknownMethodException;
use Yiisoft\Db\Exception\UnknownPropertyException;

trait BaseActiveRecordTrait
{
    /**
     * PHP getter magic method.
     *
     * This method is overridden so that attributes and related objects can be accessed like properties.
     *
     * @param string $name property name
     *
     * @throws UnknownPropertyException
     *
     * @return mixed property value
     *
     * {@see getAttribute()}
     */
    public function __get(string $name)
    {
        if (isset($this->attributes[$name]) || \array_key_exists($name, $this->attributes)) {
            return $this->attributes[$name];
        }

        if ($this->hasAttribute($name)) {
            return null;
        }

        if (isset($this->related[$name]) || \array_key_exists($name, $this->related)) {
            return $this->related[$name];
        }

        $value = $this->checkRelation($name);

        if ($value instanceof ActiveQueryInterface) {
            $this->setRelationDependencies($name, $value);
            return $this->related[$name] = $value->findFor($name, $this);
        }

        return $value;
    }

    public function checkRelation(string $name)
    {
        $getter = 'get' . $name;

        if (\method_exists($this, $getter)) {
            // read property, e.g. getName()
            return $this->$getter();
        }

        if (\method_exists($this, 'set' . $name)) {
            throw new InvalidCallException('Getting write-only property: ' . \get_class($this) . '::' . $name);
        }

        throw new UnknownPropertyException('Getting unknown property: ' . \get_class($this) . '::' . $name);
    }


    /**
     * Returns the relation object with the specified name.
     *
     * A relation is defined by a getter method which returns an {@see ActiveQueryInterface} object.
     *
     * It can be declared in either the Active Record class itself or one of its behaviors.
     *
     * @param string $name the relation name, e.g. `orders` for a relation defined via `getOrders()` method
     * (case-sensitive).
     * @param bool $throwException whether to throw exception if the relation does not exist.
     *
     * @throws InvalidArgumentException if the named relation does not exist.
     * @throws \ReflectionException
     *
     * @return ActiveQueryInterface|ActiveQuery the relational query object. If the relation does not exist and
     * `$throwException` is `false`, `null` will be returned.
     */
    public function getRelation(string $name, bool $throwException = true)
    {
        $getter = 'get' . $name;

        try {
            // the relation could be defined in a behavior
            $relation = $this->$getter();
        } catch (UnknownMethodException $e) {
            if ($throwException) {
                throw new InvalidArgumentException(
                    \get_class($this) . ' has no relation named "' . $name . '".',
                    0,
                    $e
                );
            }

            return null;
        }

        if (!$relation instanceof ActiveQueryInterface) {
            if ($throwException) {
                throw new InvalidArgumentException(\get_class($this) . ' has no relation named "' . $name . '".');
            }

            return null;
        }

        if (\method_exists($this, $getter)) {
            /* relation name is case sensitive, trying to validate it when the relation is defined within this class */
            $method = new \ReflectionMethod($this, $getter);
            $realName = \lcfirst(\substr($method->getName(), 3));

            if ($realName !== $name) {
                if ($throwException) {
                    throw new InvalidArgumentException(
                        'Relation names are case sensitive. ' . \get_class($this)
                        . " has a relation named \"$realName\" instead of \"$name\"."
                    );
                }

                return null;
            }
        }

        return $relation;
    }

    /**
     * Checks if a property value is null.
     *
     * This method overrides the parent implementation by checking if the named attribute is `null` or not.
     *
     * @param string $name the property name or the event name
     *
     * @return bool whether the property value is null
     */
    public function __isset(string $name): bool
    {
        try {
            return $this->__get($name) !== null;
        } catch (\Throwable $t) {
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Sets a component property to be null.
     *
     * This method overrides the parent implementation by clearing the specified attribute value.
     *
     * @param string $name the property name or the event name
     *
     * @throws InvalidArgumentException
     * @throws \ReflectionException
     *
     * @return void
     */
    public function __unset($name): void
    {
        if ($this->hasAttribute($name)) {
            unset($this->attributes[$name]);
            if (!empty($this->relationsDependencies[$name])) {
                $this->resetDependentRelations($name);
            }
        } elseif (\array_key_exists($name, $this->related)) {
            unset($this->related[$name]);
        } elseif ($this->getRelation($name, false) === null) {
            parent::__unset($name);
        }
    }

    /**
     * PHP setter magic method.
     *
     * This method is overridden so that AR attributes can be accessed like properties.
     *
     * @param string $name property name
     * @param mixed $value property value
     *
     * @return void
     */
    public function __set($name, $value): void
    {
        if ($this->hasAttribute($name)) {
            if (
                !empty($this->relationsDependencies[$name])
                && (!\array_key_exists($name, $this->attributes) || $this->attributes[$name] !== $value)
            ) {
                $this->resetDependentRelations($name);
            }
            $this->attributes[$name] = $value;
        }
    }

    /**
     * Returns an iterator for traversing the attributes in the ActiveRecord.
     *
     * This method is required by the interface {@see \IteratorAggregate}.
     *
     * @return \ArrayIterator an iterator for traversing the items in the list.
     */
    public function getIterator(): \ArrayIterator
    {
        $attributes = $this->getAttributes();

        return new \ArrayIterator($attributes);
    }

    /**
     * Returns whether there is an element at the specified offset.
     *
     * This method is required by the SPL interface {@see \ArrayAccess}.
     *
     * It is implicitly called when you use something like `isset($model[$offset])`.
     *
     * @param mixed $offset the offset to check on.
     *
     * @return bool whether or not an offset exists.
     */
    public function offsetExists($offset): bool
    {
        return isset($this->$offset);
    }

    /**
     * Returns the element at the specified offset.
     *
     * This method is required by the SPL interface {@see \ArrayAccess}.
     *
     * It is implicitly called when you use something like `$value = $model[$offset];`.
     *
     * @param mixed $offset the offset to retrieve element.
     *
     * @return mixed the element at the offset, null if no element is found at the offset
     */
    public function offsetGet($offset)
    {
        return $this->$offset;
    }

    /**
     * Sets the element at the specified offset.
     *
     * This method is required by the SPL interface {@see \ArrayAccess}.
     *
     * It is implicitly called when you use something like `$model[$offset] = $item;`.
     *
     * @param int $offset the offset to set element
     * @param mixed $item the element value
     *
     * @return void
     */
    public function offsetSet($offset, $item)
    {
        $this->$offset = $item;
    }

    /**
     * Sets the element value at the specified offset to null.
     *
     * This method is required by the SPL interface {@see \ArrayAccess}.
     *
     * It is implicitly called when you use something like `unset($model[$offset])`.
     *
     * @param mixed $offset the offset to unset element
     */
    public function offsetUnset($offset): void
    {
        if (\property_exists($this, $offset)) {
            $this->$offset = null;
        } else {
            unset($this->$offset);
        }
    }
}
