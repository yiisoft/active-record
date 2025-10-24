<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * Event triggered after the model has been saved to the database.
 *
 * @see ActiveRecordInterface::afterSave()
 */
final class AfterSave extends AbstractEvent
{
    /**
     * @param ActiveRecordInterface $model The model that was saved.
     */
    public function __construct(ActiveRecordInterface $model)
    {
        parent::__construct($model);
    }
}
