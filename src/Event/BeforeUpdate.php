<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * Event triggered before the record is updated in the database.
 * It allows to modify properties that will be used for {@see ActiveRecordInterface::update()} operation.
 *
 * @see ActiveRecordInterface::update()
 */
final class BeforeUpdate extends AbstractEvent
{
    /**
     * @param ActiveRecordInterface $model The model being updated.
     * @param array|null &$properties The properties that will be used for the update operation.
     */
    public function __construct(ActiveRecordInterface $model, public ?array &$properties)
    {
        parent::__construct($model);
    }
}
