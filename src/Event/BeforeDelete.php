<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

/**
 * Event triggered before the record is deleted from the database.
 *
 * @see ActiveRecordInterface::delete()
 */
final class BeforeDelete extends AbstractEvent
{
}
