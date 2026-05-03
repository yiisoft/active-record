<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * Event triggered before a new record is inserted into the database.
 * It allows modifying properties that will be used for {@see ActiveRecordInterface::insert()} operation.
 *
 * @see ActiveRecordInterface::insert()
 */
final class BeforeInsert extends AbstractEvent
{
    /**
     * @param ActiveRecordInterface $model The model that is being inserted.
     * @param array|null &$properties List of property names or name-values pairs that need to be inserted.
     * If name-value pairs are specified, the values will be used for insertion.
     * If `null`, the properties will be taken from the model.
     */
    public function __construct(ActiveRecordInterface $model, public ?array &$properties)
    {
        parent::__construct($model);
    }
}
