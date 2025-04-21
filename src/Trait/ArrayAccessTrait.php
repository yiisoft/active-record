<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use InvalidArgumentException;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\ActiveRecord\ActiveRecordModelInterface;

use function is_array;
use function property_exists;

/**
 * Trait to implement {@see ArrayAccess} interface for ActiveRecordModel.
 *
 * @method ActiveRecordInterface activeRecord()
 * @see ActiveRecordModelInterface::activeRecord()
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
        $activeRecord = $this->activeRecord();

        if ($activeRecord->hasProperty($offset)) {
            return $activeRecord->get($offset) !== null;
        }

        if (property_exists($this, $offset)) {
            return isset($this->$offset);
        }

        if ($activeRecord->isRelationPopulated($offset)) {
            return $activeRecord->relation($offset) !== null;
        }

        return false;
    }

    /**
     * @param string $offset The offset to retrieve element.
     */
    public function offsetGet(mixed $offset): mixed
    {
        $activeRecord = $this->activeRecord();

        if ($activeRecord->hasProperty($offset)) {
            return $activeRecord->get($offset);
        }

        if (property_exists($this, $offset)) {
            return $this->$offset ?? null;
        }

        return $activeRecord->relation($offset);
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
        $activeRecord = $this->activeRecord();

        if ($activeRecord->hasProperty($offset)) {
            $activeRecord->set($offset, $value);
            return;
        }

        if (property_exists($this, $offset)) {
            $this->$offset = $value;
            return;
        }

        if ($value instanceof ActiveRecordInterface || is_array($value) || $value === null) {
            $activeRecord->populateRelation($offset, $value);
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
        $activeRecord = $this->activeRecord();

        if ($activeRecord->hasProperty($offset) || property_exists($this, $offset)) {
            $activeRecord->set($offset, null);
            unset($this->$offset);
            return;
        }

        $activeRecord->resetRelation($offset);
    }
}
