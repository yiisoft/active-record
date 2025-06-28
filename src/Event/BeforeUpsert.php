<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * Event triggered before the model is upserted (inserted or updated) in the database.
 * It allows to modify properties that will be used for {@see ActiveRecordInterface::upsert()} operation.
 *
 * @see ActiveRecordInterface::upsert()
 */
final class BeforeUpsert extends AbstractEvent
{
    public function __construct(
        ActiveRecordInterface $model,
        private array|null &$insertProperties,
        private array|bool &$updateProperties,
    ) {
        parent::__construct($model);
    }

    public function &getInsertProperties(): array|null
    {
        return $this->insertProperties;
    }

    public function &getUpdateProperties(): array|bool
    {
        return $this->updateProperties;
    }
}
