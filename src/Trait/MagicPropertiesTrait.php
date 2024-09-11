<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use ReflectionException;
use Throwable;
use Yiisoft\ActiveRecord\AbstractActiveRecord;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidCallException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\UnknownPropertyException;

use function array_merge;
use function in_array;
use function method_exists;
use function property_exists;
use function ucfirst;

/**
 * Trait to define magic methods to access values of an ActiveRecord instance.
 *
 * @method array getRelatedRecords()
 * @see AbstractActiveRecord::getRelatedRecords()
 *
 * @method bool hasDependentRelations(string $name)
 * @see AbstractActiveRecord::hasDependentRelations()
 *
 * @method bool isRelationPopulated(string $name)
 * @see ActiveRecordInterface::isRelationPopulated()
 *
 * @method void resetDependentRelations(string $name)
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
    /** @psalm-var array<string, mixed> $properties */
    private array $properties = [];

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
     * {@see get()}
     */
    public function __get(string $name)
    {
        if ($this->hasProperty($name)) {
            return $this->get($name);
        }

        if ($this->isRelationPopulated($name)) {
            return $this->getRelatedRecords()[$name];
        }

        if (method_exists($this, $getter = 'get' . ucfirst($name))) {
            /** Read getter, e.g., getName() */
            return $this->$getter();
        }

        if (method_exists($this, 'get' . ucfirst($name) . 'Query')) {
            /** Read relation query getter, e.g., getUserQuery() */
            return $this->retrieveRelation($name);
        }

        if (method_exists($this, 'set' . ucfirst($name))) {
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
            unset($this->properties[$name]);

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
        if ($this->hasProperty($name)) {
            parent::set($name, $value);
            return;
        }

        if (method_exists($this, $setter = 'set' . ucfirst($name))) {
            $this->$setter($value);
            return;
        }

        if (
            method_exists($this, 'get' . ucfirst($name))
            || method_exists($this, 'get' . ucfirst($name) . 'Query')
        ) {
            throw new InvalidCallException('Setting read-only property: ' . static::class . '::' . $name);
        }

        throw new UnknownPropertyException('Setting unknown property: ' . static::class . '::' . $name);
    }

    public function hasProperty(string $name): bool
    {
        return isset($this->properties[$name]) || in_array($name, $this->properties(), true);
    }

    public function set(string $name, mixed $value): void
    {
        if ($this->hasProperty($name)) {
            parent::set($name, $value);
        } else {
            throw new InvalidArgumentException(static::class . ' has no property named "' . $name . '".');
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
        return method_exists($this, 'get' . ucfirst($name))
            || method_exists($this, 'set' . ucfirst($name))
            || method_exists($this, 'get' . ucfirst($name) . 'Query')
            || ($checkVars && property_exists($this, $name))
            || $this->hasProperty($name);
    }

    public function canGetProperty(string $name, bool $checkVars = true): bool
    {
        return method_exists($this, 'get' . ucfirst($name))
            || method_exists($this, 'get' . ucfirst($name) . 'Query')
            || ($checkVars && property_exists($this, $name))
            || $this->hasProperty($name);
    }

    public function canSetProperty(string $name, bool $checkVars = true): bool
    {
        return method_exists($this, 'set' . ucfirst($name))
            || ($checkVars && property_exists($this, $name))
            || $this->hasProperty($name);
    }

    /** @psalm-return array<string, mixed> */
    protected function valuesInternal(): array
    {
        return array_merge($this->properties, parent::valuesInternal());
    }

    protected function populateProperty(string $name, mixed $value): void
    {
        if ($name !== 'properties' && property_exists($this, $name)) {
            $this->$name = $value;
        } else {
            $this->properties[$name] = $value;
        }
    }
}
