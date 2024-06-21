<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Yiisoft\ActiveRecord\ActiveRecordInterface;

final class BeforeInsert extends AbstractEvent
{
    public function __construct(ActiveRecordInterface $model, private array|null &$properties)
    {
        parent::__construct($model);
    }

    public function &getProperties(): array|null
    {
        return $this->properties;
    }
}
