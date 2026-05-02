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
     * @param array|null $insertProperties List of property names or name-values pairs that need to be inserted.
     * If name-value pairs are specified, the values will be used for insertion.
     * If `null`, the properties will be taken from the model.
     * @param array|bool $updateProperties List of property names or name-values pairs that need to be updated
     * if the record already exists. If name-value pairs are specified, the values will be used for update.
     * If `true`, `$insertProperties` will be used for update as well. If `false`, no properties will be updated.
     */
    public function __construct(
        ActiveRecordInterface $model,
        public ?array &$insertProperties,
        public array|bool &$updateProperties,
    ) {
        parent::__construct($model);
    }
}
