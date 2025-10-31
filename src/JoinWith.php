<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Closure;

use function is_array;

final class JoinWith
{
    /**
     * @psalm-param array<string|Closure> $relations
     * @psalm-param array<string,string>|string $joinType
     */
    public function __construct(
        public readonly array $relations,
        public readonly array|bool $eagerLoading,
        private readonly array|string $joinType,
    ) {
    }

    /**
     * Returns the join type based on the relation name.
     *
     * @param string $name The relation name.
     *
     * @return string The real join type.
     */
    public function getJoinType(string $name): string
    {
        return is_array($this->joinType)
            ? ($this->joinType[$name] ?? 'INNER JOIN')
            : $this->joinType;
    }
}
