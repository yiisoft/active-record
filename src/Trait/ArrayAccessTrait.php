<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use InvalidArgumentException;
use Yiisoft\ActiveRecord\ActiveRecordInterface;

use function is_array;
use function property_exists;

/**
 * Trait to implement {@see ArrayAccess} interface for ActiveRecord.
 *
 * @method mixed get(string $name)
 * @see ActiveRecordInterface::get()
 *
 * @method bool hasProperty(string $name)
 * @see ActiveRecordInterface::hasProperty()
 *
 * @method void set(string $name, mixed $value)
 * @see ActiveRecordInterface::set()
 *
 * @method ActiveRecordInterface|array|null relation(string $name)
 * @see ActiveRecordInterface::relation()
 *
 * @method bool isRelationPopulated(string $name)
 * @see ActiveRecordInterface::isRelationPopulated()
 *
 * @method void populateRelation(string $name, ActiveRecordInterface|array|null $record)
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
     * @param string $offset The offset to check on.
     *
     * @return bool Whether an offset exists.
     */
    public function offsetExists(mixed $offset): bool
    {
        if ($this->hasProperty($offset)) {
            return $this->get($offset) !== null;
        }

        if (property_exists($this, $offset)) {
            return isset($this->$offset);
        }

        if ($this->isRelationPopulated($offset)) {
            return $this->relation($offset) !== null;
        }

        return false;
    }

    /**
     * @param string $offset The offset to retrieve element.
     */
    public function offsetGet(mixed $offset): mixed
    {
        if ($this->hasProperty($offset)) {
            return $this->get($offset);
        }

        if (property_exists($this, $offset)) {
            return $this->$offset ?? null;
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
     * @param string $offset The offset to set element.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($this->hasProperty($offset)) {
            $this->set($offset, $value);
            return;
        }

        if (property_exists($this, $offset)) {
            $this->$offset = $value;
            return;
        }

        if ($value instanceof ActiveRecordInterface || is_array($value) || $value === null) {
            $this->populateRelation($offset, $value);
            return;
        }

        throw new InvalidArgumentException('Setting unknown property: ' . static::class . '::' . $offset);
    }

    /**
     * Sets the element value at the specified offset to null.
     *
     * This method is required by the SPL interface {@see ArrayAccess}.
     *
     * It is implicitly called when you use something like `unset($model[$offset])`.
     *
     * @param string $offset The offset to unset element.
     */
    public function offsetUnset(mixed $offset): void
    {
        if ($this->hasProperty($offset) || property_exists($this, $offset)) {
            unset($this->$offset);
            return;
        }

        $this->resetRelation($offset);
    }
}
