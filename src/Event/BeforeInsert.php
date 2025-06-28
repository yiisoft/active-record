<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * Event triggered before a new record is inserted into the database.
 * It allows to modify properties that will be used for {@see ActiveRecordInterface::insert()} operation.
 *
 * @see ActiveRecordInterface::insert()
 */
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
