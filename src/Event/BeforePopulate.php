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
    public function __construct(ActiveRecordInterface $model, private array &$data)
    {
        parent::__construct($model);
    }

    public function &getData(): array
    {
        return $this->data;
    }
}
