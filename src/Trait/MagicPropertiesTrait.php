<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use ReflectionException;
use Throwable;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidCallException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\UnknownPropertyException;

use function array_diff;
use function array_flip;
use function array_intersect_key;
use function array_key_exists;
use function array_merge;
use function get_object_vars;
use function in_array;
use function method_exists;
use function property_exists;
use function ucfirst;

/**
 * Trait to define magic methods to access values of an ActiveRecord instance.
 */
trait MagicPropertiesTrait
{
    private array $attributes = [];

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
     * @throws Exception
     * @return mixed property value.
     *
     * {@see getAttribute()}
     */
    public function __get(string $name)
    {
        if ($this->hasAttribute($name)) {
            return $this->getAttribute($name);
        }

        if ($this->isRelationPopulated($name)) {
            return $this->getRelatedRecords()[$name];
        }

        if (method_exists($this, $getter = 'get' . ucfirst($name))) {
            /** read getter, e.g. getName() */
            $value = $this->$getter();

            if ($value instanceof ActiveQueryInterface) {
                return $this->retrieveRelation($name);
            }

            return $value;
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

            if ($this->hasDependentRelations($name)) {
                $this->resetDependentRelations($name);
            }
        } elseif ($this->isRelationPopulated($name)) {
            $this->resetRelation($name);
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
     * @throws InvalidCallException|UnknownPropertyException
     */
    public function __set(string $name, mixed $value): void
    {
        if ($this->hasAttribute($name)) {
            if (
                $this->hasDependentRelations($name)
                && (!array_key_exists($name, $this->attributes) || $this->attributes[$name] !== $value)
            ) {
                $this->resetDependentRelations($name);
            }

            $this->attributes[$name] = $value;
            return;
        }

        if (method_exists($this, $setter = 'set' . ucfirst($name))) {
            $this->$setter($value);
            return;
        }

        if (method_exists($this, 'get' . ucfirst($name))) {
            throw new InvalidCallException('Setting read-only property: ' . static::class . '::' . $name);
        }

        throw new UnknownPropertyException('Setting unknown property: ' . static::class . '::' . $name);
    }

    public function getAttribute(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function getAttributes(array $names = null, array $except = []): array
    {
        $names ??= $this->attributes();
        $attributes = array_merge($this->attributes, get_object_vars($this));

        if ($except !== []) {
            $names = array_diff($names, $except);
        }

        return array_intersect_key($attributes, array_flip($names));
    }

    public function hasAttribute(string $name): bool
    {
        return isset($this->attributes[$name]) || in_array($name, $this->attributes(), true);
    }

    public function isAttributeChanged(string $name, bool $identical = true): bool
    {
        if (isset($this->attributes[$name], $this->oldAttributes[$name])) {
            return $this->attributes[$name] !== $this->oldAttributes[$name];
        }

        return isset($this->attributes[$name]) || isset($this->oldAttributes[$name]);
    }

    public function setAttribute(string $name, mixed $value): void
    {
        if ($this->hasAttribute($name)) {
            if (
                $this->hasDependentRelations($name)
                && (!array_key_exists($name, $this->attributes) || $this->attributes[$name] !== $value)
            ) {
                $this->resetDependentRelations($name);
            }
            $this->attributes[$name] = $value;
        } else {
            throw new InvalidArgumentException(static::class . ' has no attribute named "' . $name . '".');
        }
    }

    /**
     * Populates an active record object using a row of data from the database/storage.
     *
     * This is an internal method meant to be called to create active record objects after fetching data from the
     * database. It is mainly used by {@see ActiveQuery} to populate the query results into active records.
     *
     * @param array|object $row Attribute values (name => value).
     */
    public function populateRecord(array|object $row): void
    {
        foreach ($row as $name => $value) {
            if (property_exists($this, $name)) {
                $this->$name = $value;
            } else {
                $this->attributes[$name] = $value;
            }

            $this->oldAttributes[$name] = $value;
        }

        $this->related = [];
        $this->relationsDependencies = [];
    }

    /**
     * Returns a value indicating whether a property is defined for this component.
     *
     * A property is defined if:
     *
     * - the class has a getter or setter method associated with the specified name (in this case, property name is
     *   case-insensitive).
     * - the class has a member variable with the specified name (when `$checkVars` is true).
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
