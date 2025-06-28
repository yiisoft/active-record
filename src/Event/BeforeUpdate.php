<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * Event triggered before the record is updated in the database.
 * It allows to modify properties that will be used for {@see ActiveRecordInterface::update()} operation.
 *
 * @see ActiveRecordInterface::update()
 */
final class BeforeUpdate extends AbstractEvent
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
