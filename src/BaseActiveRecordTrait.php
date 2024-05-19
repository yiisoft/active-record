<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Exception;
use ReflectionException;
use Throwable;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidCallException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\UnknownPropertyException;

use function array_key_exists;
use function method_exists;
use function property_exists;
use function ucfirst;

trait BaseActiveRecordTrait
{
    private static string|null $connectionId = null;

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

        /** @var mixed $value */
        $value = $this->checkRelation($name);

        if ($value instanceof ActiveQuery) {
            $this->setRelationDependencies($name, $value);
            return $this->related[$name] = $value->findFor($name, $this);
        }

        return $value;
    }

    public function checkRelation(string $name): mixed
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
        } catch (InvalidCallException|UnknownPropertyException) {
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
     *
     * @throws InvalidCallException
     */
    public function __set(string $name, mixed $value): void
    {
        if ($this->hasAttribute($name)) {
            if (
                !empty($this->relationsDependencies[$name])
                && (!array_key_exists($name, $this->attributes) || $this->attributes[$name] !== $value)
            ) {
                $this->resetDependentRelations($name);
            }
            $this->attributes[$name] = $value;
            return;
        }

        if (method_exists($this, 'get' . ucfirst($name))) {
            throw new InvalidCallException('Setting read-only property: ' . static::class . '::' . $name);
        }

        throw new UnknownPropertyException('Setting unknown property: ' . static::class . '::' . $name);
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
        } catch (Exception) {
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
        } catch (Exception) {
            /** `hasAttribute()` may fail on base/abstract classes in case automatic attribute list fetching used */
            return false;
        }
    }
}
