<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use ReflectionException;
use Throwable;
use Yiisoft\ActiveRecord\AbstractActiveRecord;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\Db\Exception\Exception;
use InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidCallException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\UnknownPropertyException;

use function array_merge;
use function in_array;
use function method_exists;
use function property_exists;

/**
 * Trait to define magic methods to access values of an ActiveRecord instance.
 *
 * @method array relatedRecords()
 * @see AbstractActiveRecord::relatedRecords()
 *
 * @method bool hasDependentRelations(string $propertyName)
 * @see AbstractActiveRecord::hasDependentRelations()
 *
 * @method bool isRelationPopulated(string $name)
 * @see ActiveRecordInterface::isRelationPopulated()
 *
 * @method void resetDependentRelations(string $propertyName)
 * @see AbstractActiveRecord::resetDependentRelations()
 *
 * @method void resetRelation(string $name)
 * @see ActiveRecordInterface::resetRelation()
 *
 * @method ActiveRecordInterface|array|null retrieveRelation(string $name)
 * @see AbstractActiveRecord::retrieveRelation()
 */
trait MagicPropertiesTrait
{
    /** @psalm-var array<string, mixed> $propertyValues */
    private array $propertyValues = [];

    /**
     * Returns a value indicating whether the record has a relation query with the specified name.
     *
     * @param string $name The name of the relation query.
     */
    public function hasRelationQuery(string $name): bool
    {
        return method_exists($this, "get{$name}Query");
    }

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

        if ($this->hasProperty($name)) {
            return $this->get($name);
        }

        if ($this->isRelationPopulated($name)) {
            return $this->relatedRecords()[$name];
        }

        if ($this->hasRelationQuery($name)) {
            /** Read relation query getter, e.g., getUserQuery() */
            return $this->retrieveRelation($name);
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
        if ($this->hasProperty($name)) {
            unset($this->propertyValues[$name]);

            if ($this->hasDependentRelations($name)) {
                $this->resetDependentRelations($name);
            }
        } elseif ($this->isRelationPopulated($name)) {
            $this->resetRelation($name);
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

        if ($this->hasProperty($name)) {
            parent::set($name, $value);
            return;
        }

        if (
            method_exists($this, "get$name")
            || $this->hasRelationQuery($name)
        ) {
            throw new InvalidCallException('Setting read-only property: ' . static::class . '::' . $name);
        }

        throw new UnknownPropertyException('Setting unknown property: ' . static::class . '::' . $name);
    }

    public function hasProperty(string $name): bool
    {
        return isset($this->propertyValues[$name]) || in_array($name, $this->propertyNames(), true);
    }

    public function set(string $propertyName, mixed $value): void
    {
        if ($this->hasProperty($propertyName)) {
            parent::set($propertyName, $value);
        } else {
            throw new InvalidArgumentException(static::class . ' has no property named "' . $propertyName . '".');
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
        return method_exists($this, "get$name")
            || method_exists($this, "set$name")
            || $this->hasRelationQuery($name)
            || ($checkVars && property_exists($this, $name))
            || $this->hasProperty($name);
    }

    public function canGetProperty(string $name, bool $checkVars = true): bool
    {
        return method_exists($this, "get$name")
            || $this->hasRelationQuery($name)
            || ($checkVars && property_exists($this, $name))
            || $this->hasProperty($name);
    }

    public function canSetProperty(string $name, bool $checkVars = true): bool
    {
        return method_exists($this, "set$name")
            || ($checkVars && property_exists($this, $name))
            || $this->hasProperty($name);
    }

    /** @psalm-return array<string, mixed> */
    protected function propertyValuesInternal(): array
    {
        return array_merge($this->propertyValues, parent::propertyValuesInternal());
    }

    protected function populateProperty(string $name, mixed $value): void
    {
        if ($name !== 'propertyValues' && property_exists($this, $name)) {
            $this->$name = $value;
        } else {
            $this->propertyValues[$name] = $value;
        }
    }
}
