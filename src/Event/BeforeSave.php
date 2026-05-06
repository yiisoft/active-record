<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * Event triggered before the model is saved (inserted or updated) to the database.
 * It allows modifying properties that will be used for {@see ActiveRecordInterface::save()} operation.
 *
 * @see ActiveRecordInterface::save()
 */
final class BeforeSave extends AbstractEvent
{
    /**
     * @param ActiveRecordInterface $model The model that is being saved.
     * @param array|null &$properties List of property names or name-values pairs that need to be saved.
     * If name-value pairs are specified, the values will be used for saving.
     * If `null`, the properties will be taken from the model.
     */
    public function __construct(ActiveRecordInterface $model, public ?array &$properties)
    {
        parent::__construct($model);
    }
}
