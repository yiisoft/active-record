<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * Event triggered after the model has been populated with data.
 *
 * @see ActiveRecordInterface::populate()
 */
final class AfterPopulate extends AbstractEvent
{
    /**
     * @param ActiveRecordInterface $model The model that has been populated.
     * @param array $data The data used to populate the model.
     */
    public function __construct(ActiveRecordInterface $model, public readonly array $data)
    {
        parent::__construct($model);
    }
}
