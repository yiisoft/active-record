<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Internal;

use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\ActiveRecord\LazyLoadGuard;

use function count;
use function spl_object_id;

/**
 * Counts the records of one result set that lazy loaded a relation. The counts are freed together with the result
 * set they belong to, which the records are the only reference to.
 *
 * @internal
 *
 * @see LazyLoadGuard
 */
final class LazyLoadResultSet
{
    /**
     * @var array[] The ids of the records that lazy loaded the relation `[relation => [record_id => true], ...]`.
     *
     * The records of a result set are known when it's registered, while all of them are alive, so an id of a
     * freed record is never reused within the same result set.
     *
     * @psalm-var array<string, array<int, true>>
     */
    private array $records = [];

    /**
     * Registers a record that lazy loaded the relation and returns the number of records of this result set that
     * have loaded it. A record that loads the relation again, after {@see ActiveRecordInterface::resetRelation()}
     * or {@see ActiveRecordInterface::refresh()} dropped it, is counted once.
     */
    public function registerLazyLoad(ActiveRecordInterface $record, string $relation): int
    {
        $this->records[$relation][spl_object_id($record)] = true;

        return count($this->records[$relation]);
    }
}
