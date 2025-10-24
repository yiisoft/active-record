<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * Event triggered after the model has been upserted (inserted or updated).
 *
 * @see ActiveRecordInterface::upsert()
 */
final class AfterUpsert extends AbstractEvent
{
    /**
     * @param ActiveRecordInterface $model The model that was upserted.
     */
    public function __construct(ActiveRecordInterface $model)
    {
        parent::__construct($model);
    }
}
