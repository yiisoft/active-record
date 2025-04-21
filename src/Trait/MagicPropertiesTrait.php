<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use ReflectionException;
use Throwable;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\ActiveRecord\ActiveRecordModelInterface;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidCallException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\UnknownPropertyException;

use function array_merge;
use function method_exists;
use function property_exists;

/**
 * Trait to define magic methods to access values of an ActiveRecord instance.
 *
 * @method ActiveRecordInterface activeRecord()
 * @see ActiveRecordModelInterface::activeRecord()
 */
trait MagicPropertiesTrait
{
    /** @psalm-var array<string, mixed> $propertyValues */
    private array $propertyValues = [];

    /**
     * PHP getter magic method.
     * This method is overridden so that values and related objects can be accessed like properties.
     *
     * @param string $name Property or relation name.
     *
     * @throws InvalidArgumentException|InvalidCallException|InvalidConfigException|ReflectionException|Throwable
     * @throws UnknownPropertyException
     *
     * @throws Exception
     * @return mixed Property or relation value.
     *
     * @see get()
     */
    public function __get(string $name)
    {
        if (method_exists($this, $getter = "get$name")) {
            /** Read getter, e.g., getName() */
            return $this->$getter();
        }

        $activeRecord = $this->activeRecord();

        if (isset($this->propertyValues[$name]) || $activeRecord->hasProperty($name)) {
            return $activeRecord->get($name);
        }

        if ($activeRecord->isRelationPopulated($name)) {
            return $activeRecord->getRelatedRecords()[$name];
        }

        if (method_exists($this, "get{$name}Query")) {
            /** Read relation query getter, e.g., getUserQuery() */
            return $activeRecord->retrieveRelation($name);
        }

        if (method_exists($this, "set$name")) {
            throw new InvalidCallException('Getting write-only property: ' . static::class . '::' . $name);
        }

        throw new UnknownPropertyException('Getting unknown property or relation: ' . static::class . '::' . $name);
    }

    /**
     * PHP isset magic method.
     * Checks if a property or relation exists and its value is not `null`.
     *
     * @param string $name The property or relation name.
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
     * PHP unset magic method.
     * Unsets the property or relation.
     *
     * @param string $name The property or relation name.
     */
    public function __unset(string $name): void
    {
        $activeRecord = $this->activeRecord();

        if (isset($this->propertyValues[$name])) {
            unset($this->propertyValues[$name]);

            $activeRecord->resetDependentRelations($name);
        } elseif ($activeRecord->isRelationPopulated($name)) {
            $activeRecord->resetRelation($name);
        }
    }

    /**
     * PHP setter magic method.
     * Sets the value of a property.
     *
     * @param string $name Property name.
     *
     * @throws InvalidCallException|UnknownPropertyException
     */
    public function __set(string $name, mixed $value): void
    {
        if (method_exists($this, $setter = "set$name")) {
            $this->$setter($value);
            return;
        }

        $activeRecord = $this->activeRecord();

        if ($activeRecord->hasProperty($name)) {
            $activeRecord->set($name, $value);
            return;
        }

        if (
            method_exists($this, "get$name")
            || method_exists($this, "get{$name}Query")
        ) {
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
     *
     * @param string $name The property name.
     * @param bool $checkVars Whether to treat member variables as properties.
     *
     * @return bool Whether the property is defined.
     *
     * {@see canGetProperty()}
     * {@see canSetProperty()}
     */
    public function isProperty(string $name, bool $checkVars = true): bool
    {
        return isset($this->propertyValues[$name])
            || ($checkVars && property_exists($this, $name))
            || method_exists($this, "get$name")
            || method_exists($this, "set$name")
            || method_exists($this, "get{$name}Query")
            || $this->activeRecord()->hasProperty($name);
    }

    public function canGetProperty(string $name, bool $checkVars = true): bool
    {
        return isset($this->propertyValues[$name])
            || ($checkVars && property_exists($this, $name))
            || method_exists($this, "get$name")
            || method_exists($this, "get{$name}Query")
            || $this->activeRecord()->hasProperty($name);
    }

    public function canSetProperty(string $name, bool $checkVars = true): bool
    {
        return isset($this->propertyValues[$name])
            || ($checkVars && property_exists($this, $name))
            || method_exists($this, "set$name")
            || $this->activeRecord()->hasProperty($name);
    }

    public function propertyValues(): array
    {
        return array_merge($this->propertyValues, parent::propertyValues());
    }

    public function populateProperty(string $name, mixed $value): void
    {
        if ($name !== 'propertyValues' && property_exists($this, $name)) {
            $this->$name = $value;
        } elseif (isset($this->propertyValues[$name]) || $this->activeRecord()->hasProperty($name)) {
            $this->propertyValues[$name] = $value;
        } else {
            throw new InvalidArgumentException(static::class . ' has no property named "' . $name . '".');
        }
    }
}
