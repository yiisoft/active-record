<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Internal;

use Closure;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\ActiveRecord\ArArrayHelper;
use Yiisoft\Db\Query\QueryInterface;

use function count;
use function is_array;

/**
 * @internal
 *
 * @psalm-import-type IndexBy from QueryInterface
 */
final class RelationPopulator
{
    private function __construct()
    {
    }

    /**
     * @psalm-param non-empty-array<ActiveRecordInterface|array> $primaryModels
     * @psalm-param-out non-empty-array<ActiveRecordInterface|array> $primaryModels
     *
     * @return ActiveRecordInterface[]|array[]
     */
    public static function populate(ActiveQueryInterface $query, string $name, array &$primaryModels): array
    {
        [$result] = self::populateInternal($query, $name, $primaryModels);
        return $result;
    }

    /**
     * @psalm-param non-empty-array<ActiveRecordInterface|array> $primaryModels
     * @psalm-param-out non-empty-array<ActiveRecordInterface|array> $primaryModels
     * @psalm-return list{array<ActiveRecordInterface>|array<array>, array<array>}
     */
    private static function populateInternal(ActiveQueryInterface $query, string $name, array &$primaryModels): array
    {
        $via = $query->getVia();
        $viaMap = [];
        if ($via instanceof ActiveQueryInterface) {
            $viaQuery = $via;
            $viaModels = JunctionRowsFinder::find($viaQuery, $primaryModels);
            ModelRelationFilter::apply($query, $viaModels);
        } elseif (is_array($via)) {
            [$viaName, $viaQuery] = $via;

            if ($viaQuery->isAsArray() === null) {
                /** inherit asArray from a primary query */
                $viaQuery->asArray($query->isAsArray());
            }

            $viaQuery->primaryModel(null);
            [$viaModels, $viaMap] = self::populateInternal($viaQuery, $viaName, $primaryModels);
            ModelRelationFilter::apply($query, $viaModels);
        } else {
            ModelRelationFilter::apply($query, $primaryModels);
        }

        if (!$query->isMultiple() && count($primaryModels) === 1) {
            /** @psalm-var list{ActiveRecordInterface|array} $models */
            $models = [$query->one()];
            self::populateInverseRelation($query, $models, $primaryModels);

            $primaryModel = $primaryModels[0];

            if ($primaryModel instanceof ActiveRecordInterface) {
                $primaryModel->populateRelation($name, $models[0]);
            } else {
                /**
                 * @psalm-var non-empty-list<array> $primaryModels
                 * @psalm-suppress UndefinedInterfaceMethod
                 */
                $primaryModels[0][$name] = $models[0];
            }

            return [$models, []];
        }

        /**
         * {@see https://github.com/yiisoft/yii2/issues/3197}
         *
         * Delay indexing related models after buckets are built.
         */
        $indexBy = $query->getIndexBy();
        $query->indexBy(null);
        /** @psalm-var array<ActiveRecordInterface|array> $models */
        $models = $query->all();

        self::populateInverseRelation($query, $models, $primaryModels);

        if (isset($viaModels, $viaQuery)) {
            [$buckets, $returnMap] = self::buildBuckets($query, $models, $viaModels, $viaQuery, $viaMap);
        } else {
            [$buckets, $returnMap] = self::buildBuckets($query, $models);
        }

        $query->indexBy($indexBy);

        if ($indexBy !== null && $query->isMultiple()) {
            $buckets = self::indexBuckets($buckets, $indexBy);
        }

        if (isset($viaQuery)) {
            $deepViaQuery = $viaQuery;

            while ($deepViaQueryVia = $deepViaQuery->getVia()) {
                $deepViaQuery = is_array($deepViaQueryVia) ? $deepViaQueryVia[1] : $deepViaQueryVia;
            }

            $link = $deepViaQuery->getLink();
        } else {
            $link = $query->getLink();
        }

        self::populateRelationFromBuckets($query, $primaryModels, $buckets, $name, $link);

        return [$models, $returnMap];
    }

    /**
     * @psalm-param array<ActiveRecordInterface|array> $models
     * @psalm-param non-empty-array<ActiveRecordInterface|array> $primaryModels
     */
    private static function populateInverseRelation(
        ActiveQueryInterface $query,
        array &$models,
        array $primaryModels,
    ): void {
        $name = $query->getInverseOf();
        if ($name === null || empty($models)) {
            return;
        }

        $model = reset($models);

        /** @var ActiveQuery $relation */
        $relation = is_array($model)
            ? $query->getModel()->relationQuery($name)
            : $model->relationQuery($name);

        $link = $relation->getLink();
        $indexBy = $relation->getIndexBy();
        [$buckets] = self::buildBuckets($relation, $primaryModels);

        if ($indexBy !== null && $relation->isMultiple()) {
            $buckets = self::indexBuckets($buckets, $indexBy);
        }

        self::populateRelationFromBuckets($relation, $models, $buckets, $name, $link);
    }

    /**
     * @param string[] $link
     *
     * @psalm-param non-empty-array<ActiveRecordInterface|array> $models
     * @psalm-param-out non-empty-array<ActiveRecordInterface|array> $models
     */
    private static function populateRelationFromBuckets(
        ActiveQueryInterface $query,
        array &$models,
        array $buckets,
        string $name,
        array $link
    ): void {
        $indexBy = $query->getIndexBy();
        $default = $query->isMultiple() ? [] : null;

        foreach ($models as &$model) {
            $keys = self::getModelKeys($model, $link);

            if (empty($keys)) {
                $value = $default;
            } elseif (count($keys) === 1) {
                $value = $buckets[$keys[0]] ?? $default;
            } else {
                if ($query->isMultiple()) {
                    $arrays = array_values(array_intersect_key($buckets, array_flip($keys)));
                    $value = $indexBy === null ? array_merge(...$arrays) : array_replace(...$arrays);
                } else {
                    $value = $default;
                }
            }

            if ($model instanceof ActiveRecordInterface) {
                $model->populateRelation($name, $value);
            } else {
                /** @var array $model */
                $model[$name] = $value;
            }
        }
    }

    /**
     * @param ActiveRecordInterface[]|array[] $models
     */
    private static function buildBuckets(
        ActiveQueryInterface $query,
        array $models,
        array|null $viaModels = null,
        ActiveQueryInterface|null $viaQuery = null,
        array $viaMap = [],
    ): array {
        $returnMap = [];
        if ($viaModels !== null) {
            $map = [];
            $linkValues = $query->getLink();
            $viaLink = $viaQuery?->getLink() ?? [];
            $viaLinkKeys = array_keys($viaLink);
            $viaVia = null;

            foreach ($viaModels as $viaModel) {
                $key1 = self::getModelKeys($viaModel, $viaLinkKeys);
                $key2 = self::getModelKeys($viaModel, $linkValues);
                $map[] = array_fill_keys($key2, array_fill_keys($key1, true));
            }

            if (!empty($map)) {
                $map = array_replace_recursive(...$map);
            }

            if ($viaQuery !== null) {
                $returnMap = $map;
                $viaVia = $viaQuery->getVia();
            }

            while ($viaVia) {
                /**
                 * @var ActiveQuery $viaViaQuery
                 *
                 * @psalm-suppress RedundantCondition
                 */
                $viaViaQuery = is_array($viaVia) ? $viaVia[1] : $viaVia;
                $map = self::mapVia($map, $viaMap);

                $viaVia = $viaViaQuery->getVia();
            }
        }

        $buckets = [];
        $linkKeys = array_keys($query->getLink());

        if (isset($map)) {
            foreach ($models as $model) {
                $keys = self::getModelKeys($model, $linkKeys);
                /** @var bool[][] $filtered */
                $filtered = array_values(array_intersect_key($map, array_fill_keys($keys, null)));

                foreach (array_keys(array_replace(...$filtered)) as $key2) {
                    $buckets[$key2][] = $model;
                }
            }
        } else {
            foreach ($models as $model) {
                $keys = self::getModelKeys($model, $linkKeys);

                foreach ($keys as $key) {
                    $buckets[$key][] = $model;
                }
            }
        }

        if (!$query->isMultiple()) {
            return [
                array_combine(
                    array_keys($buckets),
                    array_column($buckets, 0)
                ),
                $returnMap,
            ];
        }

        return [$buckets, $returnMap];
    }

    /**
     * @psalm-param array<array> $map
     * @psalm-param array<array> $viaMap
     */
    private static function mapVia(array $map, array $viaMap): array
    {
        $resultMap = [];

        foreach ($map as $key => $linkKeys) {
            $resultMap[$key] = array_replace(...array_intersect_key($viaMap, $linkKeys));
        }

        return $resultMap;
    }

    /**
     * Indexes buckets by a column name.
     *
     * @param Closure|string $indexBy the name of the column by which the query results should be indexed by. This can
     * also be a {@see Closure} that returns the index value based on the given models data.
     *
     * @psalm-param list<list<ActiveRecordInterface|array>> $buckets
     * @psalm-param IndexBy|string $indexBy
     */
    private static function indexBuckets(array $buckets, Closure|string $indexBy): array
    {
        foreach ($buckets as &$models) {
            $models = ArArrayHelper::index($models, $indexBy);
        }

        return $buckets;
    }

    /**
     * @param string[] $properties
     *
     * @psalm-return array<string|int>
     */
    private static function getModelKeys(ActiveRecordInterface|array $model, array $properties): array
    {
        $key = [];

        if (is_array($model)) {
            foreach ($properties as $property) {
                if (isset($model[$property])) {
                    /** @var array<int|string>|string */
                    $key[] = is_array($model[$property])
                        ? $model[$property]
                        : (string) $model[$property];
                }
            }
        } else {
            foreach ($properties as $property) {
                $value = $model->get($property);
                if ($value !== null) {
                    /** @var array<int|string>|string */
                    $key[] = is_array($value)
                        ? $value
                        : (string) $value;
                }
            }
        }

        return match (count($key)) {
            0 => [],
            1 => is_array($key[0]) ? $key[0] : [$key[0]],
            default => [serialize($key)],
        };
    }
}
