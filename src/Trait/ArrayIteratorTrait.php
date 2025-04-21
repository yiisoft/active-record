<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use ArrayIterator;
use IteratorAggregate;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\ActiveRecord\ActiveRecordModelInterface;

/**
 * Trait to implement {@see IteratorAggregate} interface for ActiveRecordModel.
 *
 * @method ActiveRecordInterface activeRecord()
 * @see ActiveRecordModelInterface::activeRecord()
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
        $values = $this->activeRecord()->propertyValues();

        return new ArrayIterator($values);
    }
}
