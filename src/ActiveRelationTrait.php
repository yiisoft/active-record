<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Closure;
use ReflectionException;
use Throwable;
use Yiisoft\Db\Constant\ColumnType;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\QueryBuilder\Condition\ArrayOverlapsCondition;
use Yiisoft\Db\QueryBuilder\Condition\InCondition;
use Yiisoft\Db\QueryBuilder\Condition\JsonOverlapsCondition;

use function array_column;
use function array_combine;
use function array_diff_key;
use function array_fill_keys;
use function array_filter;
use function array_flip;
use function array_intersect_key;
use function array_key_first;
use function array_keys;
use function array_merge;
use function array_unique;
use function count;
use function is_array;
use function is_object;
use function is_string;
use function key;
use function reset;
use function serialize;

/**
 * ActiveRelationTrait implements the common methods and properties for active record relational queries.
 */
trait ActiveRelationTrait
{
    private bool $multiple = false;
    private ActiveRecordInterface|null $primaryModel = null;
    /** @psalm-var string[] */
    private array $link = [];
    /**
     * @var string|null the name of the relation that is the inverse of this relation.
     *
     * For example, an order has a customer, which means the inverse of the "customer" relation is the "orders", and the
     * inverse of the "orders" relation is the "customer". If this property is set, the primary record(s) will be
     * referenced through the specified relation.
     *
     * For example, `$customer->orders[0]->customer` and `$customer` will be the same object, and accessing the customer
     * of an order will not trigger a new DB query.
     *
     * This property is only used in relational context.
     *
     * {@see inverseOf()}
     */
    private string|null $inverseOf = null;
    /**
     * @var ActiveQueryInterface|array|null the relation associated with the junction table.
     * @psalm-var array{string, ActiveQueryInterface, bool}|ActiveQueryInterface|null
     */
    private array|ActiveQueryInterface|null $via = null;
    private array $viaMap = [];

    /**
     * Clones internal objects.
     */
    public function __clone()
    {
        /** Make a clone of "via" object so that the same query object can be reused multiple times. */
        if (is_object($this->via)) {
            $this->via = clone $this->via;
        } elseif (is_array($this->via)) {
            $this->via = [$this->via[0], clone $this->via[1], $this->via[2]];
        }
    }

    /**
     * Specifies the relation associated with the junction table.
     *
     * Use this method to specify a pivot record/table when declaring a relation in the {@see ActiveRecord} class:
     *
     * ```php
     * class Order extends ActiveRecord
     * {
     *    public function getOrderItems() {
     *        return $this->hasMany(OrderItem::class, ['order_id' => 'id']);
     *    }
     *
     *    public function getItems() {
     *        return $this->hasMany(Item::class, ['id' => 'item_id'])->via('orderItems');
     *    }
     * }
     * ```
     *
     * @param string $relationName the relation name. This refers to a relation declared in {@see primaryModel}.
     * @param callable|null $callable a PHP callback for customizing the relation associated with the junction table.
     * Its signature should be `function($query)`, where `$query` is the query to be customized.
     *
     * @return static the relation object itself.
     */
    public function via(string $relationName, callable $callable = null): static
    {
        if ($this->primaryModel === null) {
            throw new InvalidConfigException('Setting via is only supported for relational queries.');
        }

        $relation = $this->primaryModel->relationQuery($relationName);
        $callableUsed = $callable !== null;
        $this->via = [$relationName, $relation, $callableUsed];

        if ($callableUsed) {
            $callable($relation);
        }

        return $this;
    }

    /**
     * Sets the name of the relation that is the inverse of this relation.
     *
     * For example, a customer has orders, which means the inverse of the "orders" relation is the "customer".
     *
     * If this property is set, the primary record(s) will be referenced through the specified relation.
     *
     * For example, `$customer->orders[0]->customer` and `$customer` will be the same object, and accessing the customer
     * of an order will not trigger a new DB query.
     *
     * Use this method when declaring a relation in the {@see ActiveRecord} class, e.g., in the Customer model:
     *
     * ```php
     * public function getOrdersQuery()
     * {
     *     return $this->hasMany(Order::class, ['customer_id' => 'id'])->inverseOf('customer');
     * }
     * ```
     *
     * This also may be used for the Order model, but with caution:
     *
     * ```php
     * public function getCustomerQuery()
     * {
     *     return $this->hasOne(Customer::class, ['id' => 'customer_id'])->inverseOf('orders');
     * }
     * ```
     *
     * in this case result will depend on how order(s) was loaded.
     * Let's suppose customer has several orders. If only one order was loaded:
     *
     * ```php
     * $orderQuery = new ActiveQuery(Order::class);
     * $orders = $orderQuery->where(['id' => 1])->all();
     * $customerOrders = $orders[0]->customer->orders;
     * ```
     *
     * variable `$customerOrders` will contain only one order. If orders was loaded like this:
     *
     * ```php
     * $orderQuery = new ActiveQuery(Order::class);
     * $orders = $orderQuery->with('customer')->where(['customer_id' => 1])->all();
     * $customerOrders = $orders[0]->customer->orders;
     * ```
     *
     * variable `$customerOrders` will contain all orders of the customer.
     *
     * @param string $relationName the name of the relation that is the inverse of this relation.
     *
     * @return static the relation object itself.
     */
    public function inverseOf(string $relationName): static
    {
        $this->inverseOf = $relationName;

        return $this;
    }

    /**
     * Returns query records depending on {@see $multiple} .
     *
     * This method is invoked when a relation of an ActiveRecord is being accessed in a lazy fashion.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws ReflectionException
     * @throws Throwable If the relation is invalid.
     *
     * @return ActiveRecordInterface|array|null The related record(s).
     */
    public function relatedRecords(): ActiveRecordInterface|array|null
    {
        return $this->multiple ? $this->all() : $this->one();
    }

    /**
     * If applicable, populate the query's primary model into the related records' inverse relationship.
     *
     * @param ActiveRecordInterface[]|array[] $result the array of related records as generated by {@see populate()}
     *
     * @throws InvalidConfigException
     *
     * @param-out ActiveRecordInterface[]|array[] $result
     */
    private function addInverseRelations(array &$result): void
    {
        if ($this->inverseOf === null) {
            return;
        }

        $relatedModel = reset($result);

        if ($relatedModel instanceof ActiveRecordInterface) {
            $inverseRelation = $relatedModel->relationQuery($this->inverseOf);
            $primaryModel = $inverseRelation->getMultiple() ? [$this->primaryModel] : $this->primaryModel;

            /** @var ActiveRecordInterface $relatedModel */
            foreach ($result as $relatedModel) {
                $relatedModel->populateRelation($this->inverseOf, $primaryModel);
            }
        } else {
            $inverseRelation = $this->getARInstance()->relationQuery($this->inverseOf);
            $primaryModel = $inverseRelation->getMultiple() ? [$this->primaryModel] : $this->primaryModel;

            /** @var array $relatedModel */
            foreach ($result as &$relatedModel) {
                $relatedModel[$this->inverseOf] = $primaryModel;
            }
        }
    }

    /**
     * @return ActiveRecordInterface[]|array[]
     *
     * @param-out ActiveRecordInterface[]|array[] $primaryModels
     */
    public function populateRelation(string $name, array &$primaryModels): array
    {
        if ($this->via instanceof ActiveQueryInterface) {
            /** @var self $viaQuery */
            $viaQuery = $this->via;
            $viaModels = $viaQuery->findJunctionRows($primaryModels);
            $this->filterByModels($viaModels);
        } elseif (is_array($this->via)) {
            [$viaName, $viaQuery] = $this->via;

            if ($viaQuery->isAsArray() === null) {
                /** inherit asArray from a primary query */
                $viaQuery->asArray($this->asArray);
            }

            $viaQuery->primaryModel(null);
            $viaModels = $viaQuery->populateRelation($viaName, $primaryModels);
            $this->filterByModels($viaModels);
        } else {
            $this->filterByModels($primaryModels);
        }

        if (!$this->multiple && count($primaryModels) === 1) {
            $models = [$this->one()];
            $this->populateInverseRelation($models, $primaryModels);

            $primaryModel = reset($primaryModels);

            if ($primaryModel instanceof ActiveRecordInterface) {
                $primaryModel->populateRelation($name, $models[0]);
            } else {
                /**
                 * @var array[] $primaryModels
                 * @psalm-suppress PossiblyNullArrayOffset
                 */
                $primaryModels[key($primaryModels)][$name] = $models[0];
            }

            return $models;
        }

        /**
         * {@see https://github.com/yiisoft/yii2/issues/3197}
         *
         * Delay indexing related models after buckets are built.
         */
        $indexBy = $this->getIndexBy();
        $this->indexBy(null);
        $models = $this->all();

        $this->populateInverseRelation($models, $primaryModels);

        if (isset($viaModels, $viaQuery)) {
            $buckets = $this->buildBuckets($models, $viaModels, $viaQuery);
        } else {
            $buckets = $this->buildBuckets($models);
        }

        $this->indexBy($indexBy);

        if ($indexBy !== null && $this->multiple) {
            $buckets = $this->indexBuckets($buckets, $indexBy);
        }

        if (isset($viaQuery)) {
            $deepViaQuery = $viaQuery;

            while ($deepViaQuery->via) {
                $deepViaQuery = is_array($deepViaQuery->via) ? $deepViaQuery->via[1] : $deepViaQuery->via;
            }

            $link = $deepViaQuery->link;
        } else {
            $link = $this->link;
        }

        $this->populateRelationFromBuckets($primaryModels, $buckets, $name, $link);

        return $models;
    }

    /**
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
     */
    private function populateInverseRelation(
        array &$models,
        array $primaryModels,
    ): void {
        if ($this->inverseOf === null || empty($models) || empty($primaryModels)) {
            return;
        }

        $name = $this->inverseOf;
        $model = reset($models);

        /** @var ActiveQuery $relation */
        $relation = is_array($model)
            ? $this->getARInstance()->relationQuery($name)
            : $model->relationQuery($name);

        $link = $relation->getLink();
        $indexBy = $relation->getIndexBy();
        $buckets = $relation->buildBuckets($primaryModels);

        if ($indexBy !== null && $relation->getMultiple()) {
            $buckets = $this->indexBuckets($buckets, $indexBy);
        }

        $relation->populateRelationFromBuckets($models, $buckets, $name, $link);
    }

    private function populateRelationFromBuckets(
        array &$models,
        array $buckets,
        string $name,
        array $link
    ): void {
        $indexBy = $this->getIndexBy();
        $default = $this->multiple ? [] : null;

        foreach ($models as &$model) {
            $keys = $this->getModelKeys($model, $link);

            /** @psalm-suppress NamedArgumentNotAllowed */
            $value = match (count($keys)) {
                0 => $default,
                1 => $buckets[$keys[0]] ?? $default,
                default => !$this->multiple
                    ? $default
                    : ($indexBy !== null
                        ? array_replace(...array_intersect_key($buckets, array_flip($keys)))
                        : array_merge(...array_intersect_key($buckets, array_flip($keys)))),
            };

            if ($model instanceof ActiveRecordInterface) {
                $model->populateRelation($name, $value);
            } else {
                /** @var array $model */
                $model[$name] = $value;
            }
        }
    }

    private function buildBuckets(
        array $models,
        array $viaModels = null,
        self $viaQuery = null
    ): array {
        if ($viaModels !== null) {
            $map = [];
            $linkValues = $this->link;
            $viaLink = $viaQuery->link ?? [];
            $viaLinkKeys = array_keys($viaLink);
            $viaVia = null;

            foreach ($viaModels as $viaModel) {
                $key1 = $this->getModelKeys($viaModel, $viaLinkKeys);
                $key2 = $this->getModelKeys($viaModel, $linkValues);
                $map[] = array_fill_keys($key2, array_fill_keys($key1, true));
            }

            if (!empty($map)) {
                $map = array_replace_recursive(...$map);
            }

            if ($viaQuery !== null) {
                $viaQuery->viaMap = $map;
                $viaVia = $viaQuery->getVia();
            }

            while ($viaVia) {
                /**
                 * @var ActiveQuery $viaViaQuery
                 *
                 * @psalm-suppress RedundantCondition
                 */
                $viaViaQuery = is_array($viaVia) ? $viaVia[1] : $viaVia;
                $map = $this->mapVia($map, $viaViaQuery->viaMap);

                $viaVia = $viaViaQuery->getVia();
            }
        }

        $buckets = [];
        $linkKeys = array_keys($this->link);

        if (isset($map)) {
            foreach ($models as $model) {
                $keys = $this->getModelKeys($model, $linkKeys);
                /** @var bool[][] $filtered */
                $filtered = array_intersect_key($map, array_fill_keys($keys, null));

                foreach (array_keys(array_replace(...$filtered)) as $key2) {
                    $buckets[$key2][] = $model;
                }
            }
        } else {
            foreach ($models as $model) {
                $keys = $this->getModelKeys($model, $linkKeys);

                foreach ($keys as $key) {
                    $buckets[$key][] = $model;
                }
            }
        }

        if (!$this->multiple) {
            return array_combine(
                array_keys($buckets),
                array_column($buckets, 0)
            );
        }

        return $buckets;
    }

    private function mapVia(array $map, array $viaMap): array
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
     */
    private function indexBuckets(array $buckets, Closure|string $indexBy): array
    {
        foreach ($buckets as &$models) {
            $models = ArArrayHelper::index($models, $indexBy);
        }

        return $buckets;
    }

    /**
     * @param array $columnNames The column names to prefix.
     *
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
     */
    private function prefixKeyColumns(array $columnNames): array
    {
        if (!empty($this->join) || !empty($this->joinWith)) {
            if (empty($this->from)) {
                $alias = $this->getARInstance()->getTableName();
            } else {
                $alias = array_key_first($this->from);

                if (!is_string($alias)) {
                    $alias = reset($this->from);
                }
            }

            foreach ($columnNames as $i => $columnName) {
                $columnNames[$i] = "$alias.$columnName";
            }
        }

        return $columnNames;
    }

    /**
     * @param ActiveRecordInterface[]|array[] $models
     *
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
     */
    protected function filterByModels(array $models): void
    {
        $properties = array_keys($this->link);
        $columnNames = $this->prefixKeyColumns($properties);

        $model = reset($models);
        $values = [];

        if (count($columnNames) === 1) {
            /** @var string $linkedProperty single key */
            $linkedProperty = reset($this->link);

            if ($model instanceof ActiveRecordInterface) {
                /** @var ActiveRecordInterface $model */
                foreach ($models as $model) {
                    $value = $model->get($linkedProperty);

                    if ($value !== null) {
                        if (is_array($value)) {
                            $values = [...$values, ...$value];
                        } else {
                            $values[] = $value;
                        }
                    }
                }
            } else {
                /** @var array $model */
                foreach ($models as $model) {
                    if (isset($model[$linkedProperty])) {
                        $value = $model[$linkedProperty];

                        if (is_array($value)) {
                            $values = [...$values, ...$value];
                        } else {
                            $values[] = $value;
                        }
                    }
                }
            }

            if (empty($values)) {
                $this->emulateExecution();
                $this->andWhere('1=0');
                return;
            }

            $scalarValues = array_filter($values, is_scalar(...));
            $nonScalarValues = array_diff_key($values, $scalarValues);

            $scalarValues = array_unique($scalarValues);
            $values = [...$scalarValues, ...$nonScalarValues];

            $columnName = reset($columnNames);
            /** @var string $propertyName */
            $propertyName = array_key_first($this->link);

            match ($this->getARInstance()->columnType($propertyName)) {
                ColumnType::ARRAY => $this->andWhere(new ArrayOverlapsCondition($columnName, $values)),
                ColumnType::JSON => $this->andWhere(new JsonOverlapsCondition($columnName, $values)),
                default => $this->andWhere(new InCondition($columnName, 'IN', $values)),
            };

            return;
        }

        $nulls = array_fill_keys($this->link, null);

        if ($model instanceof ActiveRecordInterface) {
            /** @var ActiveRecordInterface $model */
            foreach ($models as $model) {
                $value = $model->propertyValues($this->link);

                if (!empty($value)) {
                    $values[] = array_combine($columnNames, array_merge($nulls, $value));
                }
            }
        } else {
            /** @var array $model */
            foreach ($models as $model) {
                $value = array_intersect_key($model, $nulls);

                if (!empty($value)) {
                    $values[] = array_combine($columnNames, array_merge($nulls, $value));
                }
            }
        }

        if (empty($values)) {
            $this->emulateExecution();
            $this->andWhere('1=0');
            return;
        }

        $this->andWhere(new InCondition($columnNames, 'IN', $values));
    }

    private function getModelKeys(ActiveRecordInterface|array $model, array $properties): array
    {
        $key = [];

        if (is_array($model)) {
            foreach ($properties as $property) {
                if (isset($model[$property])) {
                    $key[] = is_array($model[$property])
                        ? $model[$property]
                        : (string) $model[$property];
                }
            }
        } else {
            foreach ($properties as $property) {
                $value = $model->get($property);

                if ($value !== null) {
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

    /**
     * @param ActiveRecordInterface[]|array[] $primaryModels either array of AR instances or arrays.
     *
     * @throws Exception
     * @throws Throwable
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
     * @return array[]
     */
    private function findJunctionRows(array $primaryModels): array
    {
        if (empty($primaryModels)) {
            return [];
        }

        $this->filterByModels($primaryModels);

        /** @var array[] */
        return $this->asArray()->all();
    }

    public function getMultiple(): bool
    {
        return $this->multiple;
    }

    /**
     * @return ActiveRecordInterface|null the primary model of a relational query.
     *
     * This is used only in lazy loading with dynamic query options.
     */
    public function getPrimaryModel(): ActiveRecordInterface|null
    {
        return $this->primaryModel;
    }

    /**
     * @psalm-return string[]
     */
    public function getLink(): array
    {
        return $this->link;
    }

    public function getVia(): array|ActiveQueryInterface|null
    {
        return $this->via;
    }

    public function multiple(bool $value): static
    {
        $this->multiple = $value;

        return $this;
    }

    public function primaryModel(ActiveRecordInterface|null $value): static
    {
        $this->primaryModel = $value;

        return $this;
    }

    public function link(array $value): static
    {
        $this->link = $value;

        return $this;
    }
}
