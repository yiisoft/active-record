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
    public function __construct(ActiveRecordInterface $model, private readonly int $count)
    {
        parent::__construct($model);
    }

    /**
     * @return int Number of deleted rows.
     */
    public function getCount(): int
    {
        return $this->count;
    }
}
