<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Yiisoft\ActiveRecord\ActiveRecordInterface;

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
