<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Yiisoft\Db\Exception\Exception;

/**
 * Represents an exception caused by optimistic locking failure.
 */
final class OptimisticLockException extends Exception
{
}
