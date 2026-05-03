<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * Event triggered before the record is updated in the database.
 * It allows modifying properties that will be used for {@see ActiveRecordInterface::update()} operation.
 *
 * @see ActiveRecordInterface::update()
 */
final class BeforeUpdate extends AbstractEvent
{
    /**
     * @param ActiveRecordInterface $model The model being updated.
     * @param array|null &$properties List of property names or name-values pairs that need to be updated.
     * If name-value pairs are specified, the values will be used for update.
     * If `null`, the properties will be taken from the model.
     */
    public function __construct(ActiveRecordInterface $model, public ?array &$properties)
    {
        parent::__construct($model);
    }
}
