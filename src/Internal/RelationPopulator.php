<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Internal;

use Closure;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
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
        $viaModels = null;
        $viaQuery = null;

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
        $models = $query->all();

        self::populateInverseRelation($query, $models, $primaryModels);

        [$buckets, $returnMap] = self::buildBuckets(
            $query->getLink(),
            $models,
            isset($viaModels, $viaQuery) ? [$viaModels, $viaQuery, $viaMap] : null,
        );

        $query->indexBy($indexBy);

        if ($indexBy !== null && $query->isMultiple()) {
            $buckets = self::indexBuckets($buckets, $indexBy);
        }

        if ($viaQuery !== null) {
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
        [$buckets] = self::buildBuckets($link, $primaryModels);

        if ($indexBy !== null && $relation->isMultiple()) {
            $buckets = self::indexBuckets($buckets, $indexBy);
        }

        self::populateRelationFromBuckets($relation, $models, $buckets, $name, $link);
    }

    /**
     * @psalm-param non-empty-array<ActiveRecordInterface|array> $models
     * @psalm-param-out non-empty-array<ActiveRecordInterface|array> $models
     * @psalm-param array<non-empty-array<ActiveRecordInterface|array>> $buckets
     * @psalm-param array<string, string> $link
     */
    private static function populateRelationFromBuckets(
        ActiveQueryInterface $query,
        array &$models,
        array $buckets,
        string $name,
        array $link,
    ): void {
        $indexBy = $query->getIndexBy();
        $default = $query->isMultiple() ? [] : null;

        foreach ($models as &$model) {
            $keys = self::getModelKeys($model, $link);

            if (empty($keys)) {
                $value = $default;
            } elseif (count($keys) === 1) {
                $value = $query->isMultiple()
                    ? $buckets[$keys[0]] ?? $default
                    : $buckets[$keys[0]][0] ?? $default;
            } elseif ($query->isMultiple()) {
                /** @psalm-var non-empty-list<array> $arrays */
                $arrays = array_values(array_intersect_key($buckets, array_flip($keys)));
                $value = $indexBy === null ? array_merge(...$arrays) : array_replace(...$arrays);
            } else {
                $value = $default;
            }

            if ($model instanceof ActiveRecordInterface) {
                $model->populateRelation($name, $value);
            } else {
                $model[$name] = $value;
            }
        }
    }

    /**
     * @psalm-param array<string, string> $link
     * @psalm-param array<ActiveRecordInterface|array> $models
     * @psalm-param list{array<ActiveRecordInterface|array>, ActiveQueryInterface, array<array>}|null $via
     * @psalm-return list{
     *     array<non-empty-list<ActiveRecordInterface|array>>,
     *     array<array>
     * }
     */
    private static function buildBuckets(
        array $link,
        array $models,
        ?array $via = null,
    ): array {
        $buckets = [];
        $linkKeys = array_keys($link);

        if ($via === null) {
            foreach ($models as $model) {
                $keys = self::getModelKeys($model, $linkKeys);

                foreach ($keys as $key) {
                    $buckets[$key][] = $model;
                }
            }

            return [$buckets, []];
        }

        $map = [];
        [$viaModels, $viaQuery, $viaMap] = $via;
        $viaLink = $viaQuery->getLink();
        $viaLinkKeys = array_keys($viaLink);

        foreach ($viaModels as $viaModel) {
            $key1 = self::getModelKeys($viaModel, $viaLinkKeys);
            $key2 = self::getModelKeys($viaModel, $link);
            $map[] = array_fill_keys($key2, array_fill_keys($key1, true));
        }

        if (empty($map)) {
            return [[], []];
        }

        $map = array_replace_recursive(...$map);
        /** @psalm-var array<array<true>> $map */

        if (!empty($viaMap)) {
            $map = array_map(
                static fn(array $linkKeys): array => array_replace(...array_intersect_key($viaMap, $linkKeys)),
                $map,
            );
        }

        foreach ($models as $model) {
            $keys = self::getModelKeys($model, $linkKeys);
            /** @var bool[][] $filtered */
            $filtered = array_values(array_intersect_key($map, array_fill_keys($keys, null)));

            foreach (array_keys(array_replace(...$filtered)) as $key2) {
                $buckets[$key2][] = $model;
            }
        }

        return [$buckets, $map];
    }

    /**
     * Indexes buckets by a column name.
     *
     * @param Closure|string $indexBy the name of the column by which the query results should be indexed by. This can
     * also be a {@see Closure} that returns the index value based on the given models data.
     *
     * @psalm-param array<non-empty-list<ActiveRecordInterface|array>> $buckets
     * @psalm-param IndexBy|string $indexBy
     * @psalm-return array<non-empty-array<ActiveRecordInterface|array>>
     */
    private static function indexBuckets(array $buckets, Closure|string $indexBy): array
    {
        return array_map(
            static fn(array $models) => ArArrayHelper::index($models, $indexBy),
            $buckets,
        );
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
                    $key[] = is_array($model[$property]) ? $model[$property] : (string) $model[$property];
                }
            }
        } else {
            foreach ($properties as $property) {
                $value = $model->get($property);
                if ($value !== null) {
                    /** @var array<int|string>|string */
                    $key[] = is_array($value) ? $value : (string) $value;
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
