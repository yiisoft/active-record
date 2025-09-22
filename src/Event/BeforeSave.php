<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * Event triggered before the model is saved (inserted or updated) to the database.
 * It allows to modify properties that will be used for {@see ActiveRecordInterface::save()} operation.
 *
 * @see ActiveRecordInterface::save()
 */
final class BeforeSave extends AbstractEvent
{
    /**
     * @param ActiveRecordInterface $model The model that is being saved.
     * @param array|null &$properties The properties that will be used for the save operation.
     */
    public function __construct(ActiveRecordInterface $model, public array|null &$properties)
    {
        parent::__construct($model);
    }
}
