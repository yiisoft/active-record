<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

/**
 * The interface should be implemented by Active Record classes to support optimistic locking.
 *
 * Optimistic locking allows multiple users to access the same record for edits and avoids potential conflicts. In case
 * when a user attempts to save the record upon some staled data (because another user has modified the data), a
 * {@see OptimisticLockException} exception will be thrown, and the update or deletion is skipped.
 *
 * Optimistic locking is only supported by {@see update()} and {@see delete()} methods.
 *
 * To use Optimistic locking:
 *
 * 1. Create a column to store the version number of each row. The column type should be `BIGINT DEFAULT 0`.
 *    Implement {@see optimisticLock()} method to return the name of this column.
 * 2. In the Web form that collects the user input, add a hidden field that stores the lock version of the recording
 *    being updated.
 * 3. In the controller action that does the data updating, try to catch the {@see OptimisticLockException} and
 *    implement necessary business logic (e.g., merging the changes, prompting stated data) to resolve the conflict.
 */
interface OptimisticLockInterface
{
    /**
     * Returns the name of the column that stores the lock version for implementing optimistic locking.
     *
     * @return string The column name that stores the lock version of a table row.
     */
    public function optimisticLock(): string;
}
