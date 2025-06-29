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
     * @param bool $isSuccessful Whether the save operation was successful.
     */
    public function __construct(ActiveRecordInterface $model, public bool &$isSuccessful)
    {
        parent::__construct($model);
    }
}
