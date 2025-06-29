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
    /**
     * @param ActiveRecordInterface $model The model being upserted.
     * @param array|null $insertProperties Properties to be inserted. If null, the properties will be taken from the model.
     * @param array|bool $updateProperties Properties to be updated. If false, no properties will be updated.
     * If true, `$insertProperties` will be used for update as well.
     */
    public function __construct(
        ActiveRecordInterface $model,
        public array|null &$insertProperties,
        public array|bool &$updateProperties,
    ) {
        parent::__construct($model);
    }
}
