<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Closure;

use function in_array;
use function is_array;
use function is_int;

final class JoinWith
{
    /**
     * @psalm-param array<string|Closure> $relations
     * @psalm-param array<string,string>|string $joinType
     */
    public function __construct(
        public readonly array $relations,
        private array|bool $eagerLoading,
        private readonly array|string $joinType,
    ) {
    }

    /**
     * Returns the list of relations to be loaded, filtered according to the `eagerLoading` configuration.
     *
     * If `eagerLoading` is an array, only relations whose names are present in the array will be included.
     * If `eagerLoading` is `true`, all relations will be included.
     * If `eagerLoading` is `false`, no relations will be included.
     *
     * @return array The filtered list of relations to be loaded.
     */
    public function getWith(): array
    {
        if (is_array($this->eagerLoading)) {
            $with = $this->relations;
            foreach ($with as $name => $callback) {
                if (is_int($name)) {
                    if (!in_array($callback, $this->eagerLoading, true)) {
                        unset($with[$name]);
                    }
                } elseif (!in_array($name, $this->eagerLoading, true)) {
                    unset($with[$name]);
                }
            }
            return $with;
        }

        return $this->eagerLoading ? $this->relations : [];
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

    /**
     * Creates a new instance without eager loading.
     *
     * @return self A new instance without eager loading.
     */
    public function withoutEagerLoading(): self
    {
        $new = clone $this;
        $new->eagerLoading = false;
        return $new;
    }
}
