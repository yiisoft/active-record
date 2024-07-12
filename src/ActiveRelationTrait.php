<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Closure;
use ReflectionException;
use Throwable;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;

use Yiisoft\Db\QueryBuilder\Condition\ArrayOverlapsCondition;
use Yiisoft\Db\QueryBuilder\Condition\InCondition;
use Yiisoft\Db\QueryBuilder\Condition\JsonOverlapsCondition;
use Yiisoft\Db\Schema\SchemaInterface;

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
     * of an order will not trigger new DB query.
     *
     * This property is only used in relational context.
     *
     * {@see inverseOf()}
     */
    private string|null $inverseOf = null;
    private array|ActiveQuery|null $via = null;
    private array $viaMap = [];

    /**
     * Clones internal objects.
     */
    public function __clone()
    {
        /** make a clone of "via" object so that the same query object can be reused multiple times */
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
        $relation = $this->primaryModel?->relationQuery($relationName);
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
     * Use this method when declaring a relation in the {@see ActiveRecord} class, e.g. in Customer model:
     *
     * ```php
     * public function getOrders()
     * {
     *     return $this->hasMany(Order::class, ['customer_id' => 'id'])->inverseOf('customer');
     * }
     * ```
     *
     * This also may be used for Order model, but with caution:
     *
     * ```php
     * public function getCustomer()
     * {
     *     return $this->hasOne(Customer::class, ['id' => 'customer_id'])->inverseOf('orders');
     * }
     * ```
     *
     * in this case result will depend on how order(s) was loaded.
     * Let's suppose customer has several orders. If only one order was loaded:
     *
     * ```php
     * $orderQuery = new ActiveQuery(Order::class, $db);
     * $orders = $orderQuery->where(['id' => 1])->all();
     * $customerOrders = $orders[0]->customer->orders;
     * ```
     *
     * variable `$customerOrders` will contain only one order. If orders was loaded like this:
     *
     * ```php
     * $orderQuery = new ActiveQuery(Order::class, $db);
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
     * Returns query records depends on {@see $multiple} .
     *
     * This method is invoked when a relation of an ActiveRecord is being accessed in a lazy fashion.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws ReflectionException
     * @throws Throwable if the relation is invalid.
     *
     * @return ActiveRecordInterface|array|null the related record(s).
     */
    public function relatedRecords(): ActiveRecordInterface|array|null
    {
        return $this->multiple ? $this->all() : $this->onePopulate();
    }

    /**
     * If applicable, populate the query's primary model into the related records' inverse relationship.
     *
     * @param array $result the array of related records as generated by {@see populate()}
     *
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
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

            foreach ($result as $relatedModel) {
                $relatedModel->populateRelation($this->inverseOf, $primaryModel);
            }
        } else {
            $inverseRelation = $this->getARInstance()->relationQuery($this->inverseOf);
            $primaryModel = $inverseRelation->getMultiple() ? [$this->primaryModel] : $this->primaryModel;

            foreach ($result as &$relatedModel) {
                $relatedModel[$this->inverseOf] = $primaryModel;
            }
        }
    }

    /**
     * Finds the related records and populates them into the primary models.
     *
     * @param string $name the relation name
     * @param array $primaryModels primary models
     *
     * @throws InvalidArgumentException|InvalidConfigException|NotSupportedException|Throwable if {@see link()} is
     * invalid.
     * @throws Exception
     *
     * @return array the related models
     */
    public function populateRelation(string $name, array &$primaryModels): array
    {
        if ($this->via instanceof self) {
            $viaQuery = $this->via;
            $viaModels = $viaQuery->findJunctionRows($primaryModels);
            $this->filterByModels($viaModels);
        } elseif (is_array($this->via)) {
            [$viaName, $viaQuery] = $this->via;

            if ($viaQuery->asArray === null) {
                /** inherit asArray from primary query */
                $viaQuery->asArray($this->asArray);
            }

            $viaQuery->primaryModel = null;
            $viaModels = $viaQuery->populateRelation($viaName, $primaryModels);
            $this->filterByModels($viaModels);
        } else {
            $this->filterByModels($primaryModels);
        }

        if (!$this->multiple && count($primaryModels) === 1) {
            $models = [$this->onePopulate()];
            $this->populateInverseRelation($models, $primaryModels);

            $primaryModel = reset($primaryModels);

            if ($primaryModel instanceof ActiveRecordInterface) {
                $primaryModel->populateRelation($name, $models[0]);
            } else {
                $primaryModels[key($primaryModels)][$name] = $models[0];
            }

            return $models;
        }

        /**
         * {@see https://github.com/yiisoft/yii2/issues/3197}
         *
         * delay indexing related models after buckets are built.
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
     * @param array $attributes the attributes to prefix.
     *
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
     */
    private function prefixKeyColumns(array $attributes): array
    {
        if (!empty($this->join) || !empty($this->joinWith)) {
            if (empty($this->from)) {
                $alias = $this->getARInstance()->getTableName();
            } else {
                foreach ($this->from as $alias => $table) {
                    if (!is_string($alias)) {
                        $alias = $table;
                    }
                    break;
                }
            }

            if (isset($alias)) {
                foreach ($attributes as $i => $attribute) {
                    $attributes[$i] = "$alias.$attribute";
                }
            }
        }

        return $attributes;
    }

    /**
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
     */
    protected function filterByModels(array $models): void
    {
        $attributes = array_keys($this->link);
        $attributes = $this->prefixKeyColumns($attributes);

        $model = reset($models);
        $values = [];

        if (count($attributes) === 1) {
            /** single key */
            $linkedAttribute = reset($this->link);

            if ($model instanceof ActiveRecordInterface) {
                foreach ($models as $model) {
                    $value = $model->getAttribute($linkedAttribute);

                    if ($value !== null) {
                        if (is_array($value)) {
                            $values = [...$values, ...$value];
                        } else {
                            $values[] = $value;
                        }
                    }
                }
            } else {
                foreach ($models as $model) {
                    if (isset($model[$linkedAttribute])) {
                        $value = $model[$linkedAttribute];

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

            $scalarValues = array_filter($values, 'is_scalar');
            $nonScalarValues = array_diff_key($values, $scalarValues);

            $scalarValues = array_unique($scalarValues);
            $values = [...$scalarValues, ...$nonScalarValues];

            $attribute = reset($attributes);
            $columnName = array_key_first($this->link);

            match ($this->getARInstance()->columnType($columnName)) {
                'array' => $this->andWhere(new ArrayOverlapsCondition($attribute, $values)),
                SchemaInterface::TYPE_JSON => $this->andWhere(new JsonOverlapsCondition($attribute, $values)),
                default => $this->andWhere(new InCondition($attribute, 'IN', $values)),
            };

            return;
        }

        $nulls = array_fill_keys($this->link, null);

        if ($model instanceof ActiveRecordInterface) {
            foreach ($models as $model) {
                $value = $model->getAttributes($this->link);

                if (!empty($value)) {
                    $values[] = array_combine($attributes, array_merge($nulls, $value));
                }
            }
        } else {
            foreach ($models as $model) {
                $value = array_intersect_key($model, $nulls);

                if (!empty($value)) {
                    $values[] = array_combine($attributes, array_merge($nulls, $value));
                }
            }
        }

        if (empty($values)) {
            $this->emulateExecution();
            $this->andWhere('1=0');
            return;
        }

        $this->andWhere(new InCondition($attributes, 'IN', $values));
    }

    private function getModelKeys(ActiveRecordInterface|array $activeRecord, array $attributes): array
    {
        $key = [];

        if (is_array($activeRecord)) {
            foreach ($attributes as $attribute) {
                if (isset($activeRecord[$attribute])) {
                    $key[] = is_array($activeRecord[$attribute])
                        ? $activeRecord[$attribute]
                        : (string) $activeRecord[$attribute];
                }
            }
        } else {
            foreach ($attributes as $attribute) {
                $value = $activeRecord->getAttribute($attribute);

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
     * @param array $primaryModels either array of AR instances or arrays.
     *
     * @throws Exception
     * @throws Throwable
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
     */
    private function findJunctionRows(array $primaryModels): array
    {
        if (empty($primaryModels)) {
            return [];
        }

        $this->filterByModels($primaryModels);

        /* @var $primaryModel ActiveRecord */
        $primaryModel = reset($primaryModels);

        if (!$primaryModel instanceof ActiveRecordInterface) {
            /** when primaryModels are array of arrays (asArray case) */
            $primaryModel = $this->arClass;
        }

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

    public function multiple(bool $value): self
    {
        $this->multiple = $value;

        return $this;
    }

    public function primaryModel(ActiveRecordInterface $value): self
    {
        $this->primaryModel = $value;

        return $this;
    }

    public function link(array $value): self
    {
        $this->link = $value;

        return $this;
    }
}
