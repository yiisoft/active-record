<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Internal;

use WeakMap;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\ActiveRecord\LazyLoadGuard;

use function count;

/**
 * Counts the records of one result set that lazy loaded a relation. The records are held weakly, so that the
 * counts are freed together with the result set they belong to.
 *
 * @internal
 *
 * @see LazyLoadGuard
 */
final class LazyLoadResultSet
{
    /**
     * @var WeakMap[] The records that lazy loaded the relation, by relation `[relation => WeakMap, ...]`.
     *
     * @psalm-var array<string, WeakMap<ActiveRecordInterface, true>>
     */
    private array $records = [];

    /**
     * Registers a record that lazy loaded the relation and returns the number of records of this result set that
     * have loaded it. A record that loads the relation again, after {@see ActiveRecordInterface::resetRelation()}
     * or {@see ActiveRecordInterface::refresh()} dropped it, is counted once.
     */
    public function registerLazyLoad(ActiveRecordInterface $record, string $relation): int
    {
        $records = $this->records[$relation] ?? null;

        if ($records === null) {
            /** @psalm-var WeakMap<ActiveRecordInterface, true> $records */
            $records = new WeakMap();
            $this->records[$relation] = $records;
        }

        $records[$record] = true;

        return count($records);
    }
}
