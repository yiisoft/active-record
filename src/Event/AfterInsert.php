<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Yiisoft\ActiveRecord\ActiveRecordInterface;

final class AfterInsert extends AbstractEvent
{
    public function __construct(ActiveRecordInterface $model, private readonly bool $isSuccessful)
    {
        parent::__construct($model);
    }

    /**
     * @return bool Whether the insert operation is successful.
     */
    public function isSuccessful(): bool
    {
        return $this->isSuccessful;
    }
}
