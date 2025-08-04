<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * Event triggered after the query has been created for the {@see ActiveRecordInterface} model.
 *
 * @see ActiveRecordInterface::query()
 */
final class AfterCreateQuery extends AbstractEvent
{
    public function __construct(
        ActiveRecordInterface $model,
        public ActiveQueryInterface &$query,
    ) {
        parent::__construct($model);
    }
}
