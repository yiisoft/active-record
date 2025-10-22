<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Internal;

use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * @internal
 */
final class JunctionRowsFinder
{
    private function __construct()
    {
    }

    /**
     * @param ActiveRecordInterface[]|array[] $models Either array of AR instances or arrays.
     * @return array[]
     *
     * @psalm-param array<ActiveRecordInterface|array> $models
     */
    public static function find(ActiveQueryInterface $query, array $models): array
    {
        ModelRelationFilter::apply($query, $models);

        /** @var array[] */
        return $query->asArray()->all();
    }
}
