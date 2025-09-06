<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * Event triggered before creating the query for the {@see ActiveRecordInterface} model.
 *
 * @see ActiveRecordInterface::query()
 */
final class BeforeCreateQuery extends AbstractEvent
{
    public function __construct(
        ActiveRecordInterface $model,
    ) {
        parent::__construct($model);
    }
}
