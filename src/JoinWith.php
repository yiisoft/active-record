<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Closure;

final class JoinWith
{
    /**
     * @psalm-param array<string|Closure> $relations
     * @psalm-param array<string,string>|string $joinType
     */
    public function __construct(
        public readonly array $relations,
        public readonly array|bool $eagerLoading,
        public readonly array|string $joinType,
    ) {
    }
}
