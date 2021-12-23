<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use function array_key_exists;
use ArrayAccess;
use ArrayIterator;
use Error;
use Exception;
use IteratorAggregate;
use function lcfirst;
use function method_exists;
use function property_exists;
use ReflectionException;
use ReflectionMethod;
use function substr;

use Throwable;
use function ucfirst;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidCallException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\UnknownPropertyException;

trait BaseActiveRecordTrait
{
    private static ?string $connectionId = null;

    /**
     * PHP getter magic method.
     *
     * This method is overridden so that attributes and related objects can be accessed like properties.
     *
     * @param string $name property name.
     *
     * @throws InvalidArgumentException|InvalidCallException|InvalidConfigException|ReflectionException|Throwable
     * @throws UnknownPropertyException
     *
     * @return mixed property value.
     *
     * {@see getAttribute()}
     */
    public function __get(string $name)
    {
        if (isset($this->attributes[$name]) || array_key_exists($name, $this->attributes)) {
            return $this->attributes[$name];
        }

        if ($this->hasAttribute($name)) {
            return null;
        }

        if (isset($this->related[$name]) || array_key_exists($name, $this->related)) {
            return $this->related[$name];
        }

        $value = $this->checkRelation($name);

        if ($value instanceof ActiveQuery) {
            $this->setRelationDependencies($name, $value);
            return $this->related[$name] = $value->findFor($name, $this);
        }

        return $value;
    }

    public function checkRelation(string $name)
    {
        $getter = 'get' . ucfirst($name);

        if (method_exists($this, $getter)) {
            /** read property, e.g. getName() */
            return $this->$getter();
        }

        if (method_exists($this, 'set' . ucfirst($name))) {
            throw new InvalidCallException('Getting write-only property: ' . static::class . '::' . $name);
        }

        throw new UnknownPropertyException('Getting unknown property: ' . static::class . '::' . $name);
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
     * @throws InvalidArgumentException|ReflectionException if the named relation does not exist.
     *
     * @return ActiveQuery|null the relational query object. If the relation does not exist and
     * `$throwException` is `false`, `null` will be returned.
     */
    public function getRelation(string $name, bool $throwException = true): ?ActiveQuery
    {
        $getter = 'get' . ucfirst($name);

        try {
            /** the relation could be defined in a behavior */
            $relation = $this->$getter();
        } catch (Error $e) {
            if ($throwException) {
                throw new InvalidArgumentException(static::class . ' has no relation named "' . $name . '".');
            }

            return null;
        }

        if (!$relation instanceof ActiveQueryInterface) {
            if ($throwException) {
                throw new InvalidArgumentException(static::class . ' has no relation named "' . $name . '".');
            }

            return null;
        }

        if (method_exists($this, $getter)) {
            /** relation name is case sensitive, trying to validate it when the relation is defined within this class */
            $method = new ReflectionMethod($this, $getter);
            $realName = lcfirst(substr($method->getName(), 3));

            if ($realName !== $name) {
                if ($throwException) {
                    throw new InvalidArgumentException(
                        'Relation names are case sensitive. ' . static::class
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
     * @param string $name the property name or the event name.
     *
     * @return bool whether the property value is null.
     */
    public function __isset(string $name): bool
    {
        try {
            return $this->__get($name) !== null;
        } catch (Throwable $t) {
            return false;
        }
    }

    /**
     * Sets a component property to be null.
     *
     * This method overrides the parent implementation by clearing the specified attribute value.
     *
     * @param string $name the property name or the event name.
     */
    public function __unset(string $name): void
    {
        if ($this->hasAttribute($name)) {
            unset($this->attributes[$name]);
            if (!empty($this->relationsDependencies[$name])) {
                $this->resetDependentRelations($name);
            }
        } elseif (array_key_exists($name, $this->related)) {
            unset($this->related[$name]);
        }
    }

    /**
     * PHP setter magic method.
     *
     * This method is overridden so that AR attributes can be accessed like properties.
     *
     * @param string $name property name.
     * @param mixed $value property value.
     *
     * @throws InvalidCallException
     */
    public function __set(string $name, $value): void
    {
        if ($this->hasAttribute($name)) {
            if (
                !empty($this->relationsDependencies[$name])
                && (!array_key_exists($name, $this->attributes) || $this->attributes[$name] !== $value)
            ) {
                $this->resetDependentRelations($name);
            }
            $this->attributes[$name] = $value;
        }

        if (method_exists($this, 'get' . ucfirst($name))) {
            throw new InvalidCallException('Setting read-only property: ' . static::class . '::' . $name);
        }
    }

    /**
     * Returns an iterator for traversing the attributes in the ActiveRecord.
     *
     * This method is required by the interface {@see IteratorAggregate}.
     *
     * @return ArrayIterator an iterator for traversing the items in the list.
     */
    public function getIterator(): ArrayIterator
    {
        $attributes = $this->getAttributes();

        return new ArrayIterator($attributes);
    }

    /**
     * Returns whether there is an element at the specified offset.
     *
     * This method is required by the SPL interface {@see ArrayAccess}.
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
     * This method is required by the SPL interface {@see ArrayAccess}.
     *
     * It is implicitly called when you use something like `$value = $model[$offset];`.
     *
     * @param mixed $offset the offset to retrieve element.
     *
     * @return mixed the element at the offset, null if no element is found at the offset
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->$offset;
    }

    /**
     * Sets the element at the specified offset.
     *
     * This method is required by the SPL interface {@see ArrayAccess}.
     *
     * It is implicitly called when you use something like `$model[$offset] = $item;`.
     *
     * @param mixed $offset the offset to set element.
     * @param mixed $item the element value.
     */
    public function offsetSet($offset, $item): void
    {
        $this->$offset = $item;
    }

    /**
     * Sets the element value at the specified offset to null.
     *
     * This method is required by the SPL interface {@see ArrayAccess}.
     *
     * It is implicitly called when you use something like `unset($model[$offset])`.
     *
     * @param mixed $offset the offset to unset element
     */
    public function offsetUnset($offset): void
    {
        if (property_exists($this, $offset)) {
            $this->$offset = null;
        } else {
            unset($this->$offset);
        }
    }

    /**
     * Returns a value indicating whether a property is defined for this component.
     *
     * A property is defined if:
     *
     * - the class has a getter or setter method associated with the specified name (in this case, property name is
     *   case-insensitive).
     * - the class has a member variable with the specified name (when `$checkVars` is true).
     * - an attached behavior has a property of the given name (when `$checkBehaviors` is true).
     *
     * @param string $name the property name.
     * @param bool $checkVars whether to treat member variables as properties.
     *
     * @return bool whether the property is defined.
     *
     * {@see canGetProperty()}
     * {@see canSetProperty()}
     */
    public function hasProperty(string $name, bool $checkVars = true): bool
    {
        return $this->canGetProperty($name, $checkVars)
            || $this->canSetProperty($name, false);
    }

    public function canGetProperty(string $name, bool $checkVars = true): bool
    {
        if (method_exists($this, 'get' . ucfirst($name)) || ($checkVars && property_exists($this, $name))) {
            return true;
        }

        try {
            return $this->hasAttribute($name);
        } catch (Exception $e) {
            /** `hasAttribute()` may fail on base/abstract classes in case automatic attribute list fetching used */
            return false;
        }
    }

    public function canSetProperty(string $name, bool $checkVars = true): bool
    {
        if (method_exists($this, 'set' . ucfirst($name)) || ($checkVars && property_exists($this, $name))) {
            return true;
        }

        try {
            return $this->hasAttribute($name);
        } catch (Exception $e) {
            /** `hasAttribute()` may fail on base/abstract classes in case automatic attribute list fetching used */
            return false;
        }
    }
}
