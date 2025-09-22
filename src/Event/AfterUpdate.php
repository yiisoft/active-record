<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * Event triggered after the record has been updated in the database.
 *
 * @see ActiveRecordInterface::update()
 */
final class AfterUpdate extends AbstractEvent
{
    /**
     * @param ActiveRecordInterface $model The model that was updated.
     * @param int $count The number of rows that were updated.
     */
    public function __construct(ActiveRecordInterface $model, public int &$count)
    {
        parent::__construct($model);
    }
}
