<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * Event triggered before the model is populated with data.
 * It allows to modify the data that will be used for {@see ActiveRecordInterface::populateRecord()} operation.
 *
 * @see ActiveRecordInterface::populateRecord()
 */
final class BeforePopulate extends AbstractEvent
{
    /**
     * @param ActiveRecordInterface $model The model that will be populated.
     * @param array &$data The data that will be used to populate the model.
     */
    public function __construct(ActiveRecordInterface $model, public array &$data)
    {
        parent::__construct($model);
    }
}
