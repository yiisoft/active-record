<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs;

use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\BaseActiveRecord;
use Yiisoft\Db\Exception\NotSupportedException;

/**
 * ActiveRecord is the base class for classes representing relational data in terms of objects.
 *
 * This class implements the ActiveRecord pattern for the [redis](http://redis.io/) key-value store.
 *
 * For defining a record a subclass should at least implement the {@see attributes()} method to define attributes. A
 * primary key can be defined via {@see primaryKey()} which defaults to `id` if not specified.
 *
 * The following is an example model called `Customer`:
 *
 * ```php
 * class Customer extends \Yiisoft\Db\Redis\ActiveRecord
 * {
 *     public function attributes()
 *     {
 *         return ['id', 'name', 'address', 'registration_date'];
 *     }
 * }
 * ```
 */
class DummyActiveRecord extends BaseActiveRecord
{
    public function primaryKey(): array
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported.');
    }

    public function attributes(): array
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported.');
    }

    public function insert(?array $attributes = null): bool
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported.');
    }
}
