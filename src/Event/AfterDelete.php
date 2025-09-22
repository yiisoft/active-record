<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * Event triggered after the record has been deleted from the database.
 *
 * @see ActiveRecordInterface::delete()
 */
final class AfterDelete extends AbstractEvent
{
    /**
     * @param ActiveRecordInterface $model The model that was deleted.
     * @param int $count Number of deleted rows.
     */
    public function __construct(ActiveRecordInterface $model, public int &$count)
    {
        parent::__construct($model);
    }
}
