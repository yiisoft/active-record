<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Yiisoft\ActiveRecord\ActiveRecordInterface;

final class AfterUpdate extends AbstractEvent
{
    public function __construct(ActiveRecordInterface $model, private readonly int $count)
    {
        parent::__construct($model);
    }

    /**
     * @return int Number of updated rows.
     */
    public function getCount(): int
    {
        return $this->count;
    }
}
