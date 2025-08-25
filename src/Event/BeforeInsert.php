<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * Event triggered before a new record is inserted into the database.
 * It allows to modify properties that will be used for {@see ActiveRecordInterface::insert()} operation.
 *
 * @see ActiveRecordInterface::insert()
 */
final class BeforeInsert extends AbstractEvent
{
    /**
     * @param ActiveRecordInterface $model The model that is being inserted.
     * @param array|null &$properties The properties that will be used for the insert operation.
     */
    public function __construct(ActiveRecordInterface $model, public array|null &$properties)
    {
        parent::__construct($model);
    }
}
