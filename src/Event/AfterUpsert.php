<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * Event triggered after the model has been upserted (inserted or updated).
 *
 * @see ActiveRecordInterface::upsert()
 */
final class AfterUpsert extends AbstractEvent
{
    public function __construct(ActiveRecordInterface $model, private readonly bool $isSuccessful)
    {
        parent::__construct($model);
    }

    /**
     * @return bool Whether the operation is successful.
     */
    public function isSuccessful(): bool
    {
        return $this->isSuccessful;
    }
}
