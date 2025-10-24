<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * Event triggered after the record has been inserted into the database.
 *
 * @see ActiveRecordInterface::insert
 */
final class AfterInsert extends AbstractEvent
{
    /**
     * @param ActiveRecordInterface $model The model that has been inserted.
     */
    public function __construct(ActiveRecordInterface $model)
    {
        parent::__construct($model);
    }
}
