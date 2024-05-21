<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use InvalidArgumentException;
use Yiisoft\ActiveRecord\ActiveRecordInterface;

use function get_class;
use function get_object_vars;
use function property_exists;

/**
 * Trait to implement {@see ArrayAccess} interface for ActiveRecord.
 *
 * @method mixed getAttribute(string $name)
 * @see ActiveRecordInterface::getAttribute()
 *
 * @method bool hasAttribute(string $name)
 * @see ActiveRecordInterface::hasAttribute()
 *
 * @method void setAttribute(string $name, mixed $value)
 * @see ActiveRecordInterface::getAttribute()
 *
 * @method ActiveRecordInterface relation(string $name)
 * @see ActiveRecordInterface::relation()
 *
 * @method bool isRelationPopulated(string $name)
 * @see ActiveRecordInterface::isRelationPopulated()
 *
 * @method void populateRelation(string $name, ActiveRecordInterface $record)
 * @see ActiveRecordInterface::populateRelation()
 *
 * @method void resetRelation(string $name)
 * @see ActiveRecordInterface::resetRelation()
 */
trait ArrayAccessTrait
{
    /**
     * Returns whether there is an element at the specified offset.
     *
     * This method is required by the SPL interface {@see ArrayAccess}.
     *
     * It is implicitly called when you use something like `isset($model[$offset])`.
     *
     * @param string $offset the offset to check on.
     *
     * @return bool whether or not an offset exists.
     */
    public function offsetExists(mixed $offset): bool
    {
        if ($this->hasAttribute($offset)) {
            return $this->getAttribute($offset) !== null;
        }

        if (property_exists($this, $offset)) {
            return isset(get_object_vars($this)[$offset]);
        }

        if ($this->isRelationPopulated($offset)) {
            return $this->relation($offset) !== null;
        }

        return false;
    }

    /**
     * @param string $offset the offset to retrieve element.
     */
    public function offsetGet(mixed $offset): mixed
    {
        if ($this->hasAttribute($offset)) {
            return $this->getAttribute($offset);
        }

        if (property_exists($this, $offset)) {
            return get_object_vars($this)[$offset] ?? null;
        }

        return $this->relation($offset);
    }

    /**
     * Sets the element at the specified offset.
     *
     * This method is required by the SPL interface {@see ArrayAccess}.
     *
     * It is implicitly called when you use something like `$model[$offset] = $item;`.
     *
     * @param string $offset the offset to set element.
     * @param mixed $value the element value.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($this->hasAttribute($offset)) {
            $this->setAttribute($offset, $value);
            return;
        }

        if (property_exists($this, $offset)) {
            $this->$offset = $value;
            return;
        }

        if ($value instanceof ActiveRecordInterface) {
            $this->populateRelation($offset, $value);
            return;
        }

        throw new InvalidArgumentException('Setting unknown property: ' . get_class($this) . '::' . $offset);
    }

    /**
     * Sets the element value at the specified offset to null.
     *
     * This method is required by the SPL interface {@see ArrayAccess}.
     *
     * It is implicitly called when you use something like `unset($model[$offset])`.
     *
     * @param string $offset the offset to unset element
     */
    public function offsetUnset(mixed $offset): void
    {
        if ($this->hasAttribute($offset)) {
            $this->setAttribute($offset, null);
            return;
        }

        if (property_exists($this, $offset)) {
            $this->$offset = null;
            return;
        }

        $this->resetRelation($offset);
    }
}
