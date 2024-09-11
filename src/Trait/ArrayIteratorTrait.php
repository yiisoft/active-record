<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use ArrayIterator;
use IteratorAggregate;

/**
 * Trait to implement {@see IteratorAggregate} interface for ActiveRecord.
 *
 * @method array values(array|null $names = null, array $except = [])
 * @see ActiveRecordInterface::values()
 */
trait ArrayIteratorTrait
{
    /**
     * Returns an iterator for traversing the properties in the ActiveRecord.
     *
     * This method is required by the interface {@see IteratorAggregate}.
     *
     * @return ArrayIterator an iterator for traversing the items in the list.
     */
    public function getIterator(): ArrayIterator
    {
        $values = $this->values();

        return new ArrayIterator($values);
    }
}
