<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use LogicException;
use Yiisoft\FriendlyException\FriendlyExceptionInterface;

/**
 * Represents an exception thrown by {@see LazyLoadGuard} in the {@see LazyLoadGuardMode::Strict} mode when several
 * records of one result set lazy load the same relation.
 */
final class LazyLoadException extends LogicException implements FriendlyExceptionInterface
{
    public function __construct(string $message, private readonly string $relationName)
    {
        parent::__construct($message);
    }

    public function getName(): string
    {
        return 'Relation is lazy loaded by several records of one result set';
    }

    public function getSolution(): ?string
    {
        return <<<SOLUTION
            Every record runs a query of its own to load the relation. Eager load it with the query that reads
            the records, so that the relation is read with a single query:

            ```php
            \$customers = Customer::query()->with('$this->relationName')->all();
            ```

            When the lazy loads are intentional, switch the guard to `LazyLoadGuardMode::Log` to keep reporting
            them without throwing, or to `LazyLoadGuardMode::Disabled` to stop reporting them.
            SOLUTION;
    }
}
