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
    public function __construct(ActiveRecordInterface $model, private readonly array $data)
    {
        parent::__construct($model);
    }

    public function getData(): array
    {
        return $this->data;
    }
}
