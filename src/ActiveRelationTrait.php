<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use ReflectionException;
use ReflectionMethod;
use Stringable;
use Throwable;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Expression\ArrayExpression;

use function array_combine;
use function array_keys;
use function array_merge;
use function array_unique;
use function array_values;
use function count;
use function is_array;
use function is_object;
use function is_scalar;
use function is_string;
use function key;
use function lcfirst;
use function method_exists;
use function reset;
use function serialize;

/**
 * ActiveRelationTrait implements the common methods and properties for active record relational queries.
 *
 * @method ActiveRecordInterface one()
 * @method ActiveRecordInterface[] all()
 *
 * @property ActiveRecord $modelClass
 */
trait ActiveRelationTrait
{
    private bool $multiple = false;
    private ActiveRecordInterface|null $primaryModel = null;
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
     * @param callable|null $callable $callable a PHP callback for customizing the relation associated with the junction
     * table.
     * Its signature should be `function($query)`, where `$query` is the query to be customized.
     *
     * @return static the relation object itself.
     */
    public function via(string $relationName, callable $callable = null): static
    {
        $relation = $this->primaryModel->getRelation($relationName);
        $callableUsed = $callable !== null;
        $this->via = [$relationName, $relation, $callableUsed];

        if ($callable !== null) {
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
     * Finds the related records for the specified primary record.
     *
     * This method is invoked when a relation of an ActiveRecord is being accessed in a lazy fashion.
     *
     * @param string $name the relation name.
     * @param ActiveRecordInterface $model the primary model.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws ReflectionException
     * @throws Throwable if the relation is invalid.
     *
     * @return array|object|null the related record(s).
     */
    public function findFor(string $name, ActiveRecordInterface $model): array|null|object
    {
        if (method_exists($model, 'get' . $name)) {
            $method = new ReflectionMethod($model, 'get' . $name);
            $realName = lcfirst(substr($method->getName(), 3));
            if ($realName !== $name) {
                throw new InvalidArgumentException(
                    'Relation names are case sensitive. ' . $model::class
                    . " has a relation named \"$realName\" instead of \"$name\"."
                );
            }
        }

        return $this->multiple ? $this->all() : $this->one();
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

        foreach ($result as $i => $relatedModel) {
            if ($relatedModel instanceof ActiveRecordInterface) {
                if (!isset($inverseRelation)) {
                    /** @var ActiveQuery $inverseRelation */
                    $inverseRelation = $relatedModel->getRelation($this->inverseOf);
                }
                $relatedModel->populateRelation(
                    $this->inverseOf,
                    $inverseRelation->multiple ? [$this->primaryModel] : $this->primaryModel
                );
            } else {
                if (!isset($inverseRelation)) {
                    /** @var ActiveQuery $inverseRelation */
                    $inverseRelation = $this->getARInstance()->getRelation($this->inverseOf);
                }

                $result[$i][$this->inverseOf] = $inverseRelation->multiple
                    ? [$this->primaryModel] : $this->primaryModel;
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
            $model = $this->one();
            $primaryModel = reset($primaryModels);

            if ($primaryModel instanceof ActiveRecordInterface) {
                $primaryModel->populateRelation($name, $model);
            } else {
                $primaryModels[key($primaryModels)][$name] = $model;
            }

            if ($this->inverseOf !== null) {
                $this->populateInverseRelation($primaryModels, [$model], $name, $this->inverseOf);
            }

            return [$model];
        }

        /**
         * {@see https://github.com/yiisoft/yii2/issues/3197}
         *
         * delay indexing related models after buckets are built.
         */
        $indexBy = $this->getIndexBy();
        $this->indexBy(null);
        $models = $this->all();

        if (isset($viaModels, $viaQuery)) {
            $buckets = $this->buildBuckets($models, $this->link, $viaModels, $viaQuery);
        } else {
            $buckets = $this->buildBuckets($models, $this->link);
        }

        $this->indexBy($indexBy);

        if ($this->getIndexBy() !== null && $this->multiple) {
            $buckets = $this->indexBuckets($buckets, $this->getIndexBy());
        }

        $link = array_values($this->link);
        if (isset($viaQuery)) {
            $deepViaQuery = $viaQuery;

            while ($deepViaQuery->via) {
                $deepViaQuery = is_array($deepViaQuery->via) ? $deepViaQuery->via[1] : $deepViaQuery->via;
            }

            $link = array_values($deepViaQuery->link);
        }

        foreach ($primaryModels as $i => $primaryModel) {
            $keys = null;
            if ($this->multiple && count($link) === 1) {
                $primaryModelKey = reset($link);
                $keys = $primaryModel[$primaryModelKey] ?? null;
            }
            if (is_array($keys)) {
                $value = [];
                foreach ($keys as $key) {
                    $key = $this->normalizeModelKey($key);
                    if (isset($buckets[$key])) {
                        if ($this->getIndexBy() !== null) {
                            /** if indexBy is set, array_merge will cause renumbering of numeric array */
                            foreach ($buckets[$key] as $bucketKey => $bucketValue) {
                                $value[$bucketKey] = $bucketValue;
                            }
                        } else {
                            $value = array_merge($value, $buckets[$key]);
                        }
                    }
                }
            } else {
                $key = $this->getModelKey($primaryModel, $link);
                $value = $buckets[$key] ?? ($this->multiple ? [] : null);
            }

            if ($primaryModel instanceof ActiveRecordInterface) {
                $primaryModel->populateRelation($name, $value);
            } else {
                $primaryModels[$i][$name] = $value;
            }
        }
        if ($this->inverseOf !== null) {
            $this->populateInverseRelation($primaryModels, $models, $name, $this->inverseOf);
        }

        return $models;
    }

    /**
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
     */
    private function populateInverseRelation(
        array &$primaryModels,
        array $models,
        string $primaryName,
        string $name
    ): void {
        if (empty($models) || empty($primaryModels)) {
            return;
        }

        $model = reset($models);

        if ($model instanceof ActiveRecordInterface) {
            /** @var ActiveQuery $relation */
            $relation = $model->getRelation($name);
        } else {
            /** @var ActiveQuery $relation */
            $relation = $this->getARInstance()->getRelation($name);
        }

        if ($relation->multiple) {
            $buckets = $this->buildBuckets($primaryModels, $relation->link, null, null, false);
            if ($model instanceof ActiveRecordInterface) {
                foreach ($models as $model) {
                    $key = $this->getModelKey($model, $relation->link);
                    $model->populateRelation($name, $buckets[$key] ?? []);
                }
            } else {
                foreach ($primaryModels as $i => $primaryModel) {
                    if ($this->multiple) {
                        foreach ($primaryModel as $j => $m) {
                            $key = $this->getModelKey($m, $relation->link);
                            $primaryModels[$i][$j][$name] = $buckets[$key] ?? [];
                        }
                    } elseif (!empty($primaryModel[$primaryName])) {
                        $key = $this->getModelKey($primaryModel[$primaryName], $relation->link);
                        $primaryModels[$i][$primaryName][$name] = $buckets[$key] ?? [];
                    }
                }
            }
        } elseif ($this->multiple) {
            foreach ($primaryModels as $i => $primaryModel) {
                foreach ($primaryModel[$primaryName] as $j => $m) {
                    if ($m instanceof ActiveRecordInterface) {
                        $m->populateRelation($name, $primaryModel);
                    } else {
                        $primaryModels[$i][$primaryName][$j][$name] = $primaryModel;
                    }
                }
            }
        } else {
            foreach ($primaryModels as $i => $primaryModel) {
                if ($primaryModel[$primaryName] instanceof ActiveRecordInterface) {
                    $primaryModel[$primaryName]->populateRelation($name, $primaryModel);
                } elseif (!empty($primaryModel[$primaryName])) {
                    $primaryModels[$i][$primaryName][$name] = $primaryModel;
                }
            }
        }
    }

    private function buildBuckets(
        array $models,
        array $link,
        array $viaModels = null,
        self $viaQuery = null,
        bool $checkMultiple = true
    ): array {
        if ($viaModels !== null) {
            $map = [];
            $viaLink = $viaQuery->link;
            $viaLinkKeys = array_keys($viaLink);
            $linkValues = array_values($link);

            foreach ($viaModels as $viaModel) {
                $key1 = $this->getModelKey($viaModel, $viaLinkKeys);
                $key2 = $this->getModelKey($viaModel, $linkValues);
                $map[$key2][$key1] = true;
            }

            $viaQuery->viaMap = $map;

            $viaVia = $viaQuery->via;
            while ($viaVia) {
                $viaViaQuery = is_array($viaVia) ? $viaVia[1] : $viaVia;
                $map = $this->mapVia($map, $viaViaQuery->viaMap);

                $viaVia = $viaViaQuery->via;
            }
        }

        $buckets = [];
        $linkKeys = array_keys($link);

        if (isset($map)) {
            foreach ($models as $model) {
                $key = $this->getModelKey($model, $linkKeys);
                if (isset($map[$key])) {
                    foreach (array_keys($map[$key]) as $key2) {
                        $buckets[$key2][] = $model;
                    }
                }
            }
        } else {
            foreach ($models as $model) {
                $key = $this->getModelKey($model, $linkKeys);
                $buckets[$key][] = $model;
            }
        }

        if ($checkMultiple && !$this->multiple) {
            foreach ($buckets as $i => $bucket) {
                $buckets[$i] = reset($bucket);
            }
        }

        return $buckets;
    }

    private function mapVia(array $map, array $viaMap): array
    {
        $resultMap = [];

        foreach ($map as $key => $linkKeys) {
            foreach (array_keys($linkKeys) as $linkKey) {
                $resultMap[$key] = $viaMap[$linkKey];
            }
        }

        return $resultMap;
    }

    /**
     * Indexes buckets by column name.
     *
     * @param callable|string $indexBy the name of the column by which the query results should be indexed by. This can
     * also be a callable(e.g. anonymous function) that returns the index value based on the given row data.
     */
    private function indexBuckets(array $buckets, callable|string $indexBy): array
    {
        $result = [];

        foreach ($buckets as $key => $models) {
            $result[$key] = [];
            foreach ($models as $model) {
                $index = is_string($indexBy) ? $model[$indexBy] : $indexBy($model);
                $result[$key][$index] = $model;
            }
        }

        return $result;
    }

    /**
     * @param array $attributes the attributes to prefix.
     *
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
     */
    private function prefixKeyColumns(array $attributes): array
    {
        if ((!empty($this->join) || !empty($this->joinWith))) {
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

        $values = [];
        if (count($attributes) === 1) {
            /** single key */
            $attribute = reset($this->link);
            foreach ($models as $model) {
                $value = isset($model[$attribute]) || (is_object($model) && property_exists($model, $attribute)) ? $model[$attribute] : null;
                if ($value !== null) {
                    if (is_array($value)) {
                        $values = array_merge($values, $value);
                    } elseif ($value instanceof ArrayExpression && $value->getDimension() === 1) {
                        $values = array_merge($values, $value->getValue());
                    } else {
                        $values[] = $value;
                    }
                }
            }

            if (empty($values)) {
                $this->emulateExecution();
            }
        } else {
            /**
             * composite keys ensure keys of $this->link are prefixed the same way as $attributes.
             */
            $prefixedLink = array_combine($attributes, $this->link);

            foreach ($models as $model) {
                $v = [];

                foreach ($prefixedLink as $attribute => $link) {
                    $v[$attribute] = $model[$link];
                }

                $values[] = $v;

                if (empty($v)) {
                    $this->emulateExecution();
                }
            }
        }

        if (!empty($values)) {
            $scalarValues = [];
            $nonScalarValues = [];
            foreach ($values as $value) {
                if (is_scalar($value)) {
                    $scalarValues[] = $value;
                } else {
                    $nonScalarValues[] = $value;
                }
            }

            $scalarValues = array_unique($scalarValues);
            $values = [...$scalarValues, ...$nonScalarValues];
        }

        $this->andWhere(['in', $attributes, $values]);
    }

    private function getModelKey(ActiveRecordInterface|array $activeRecord, array $attributes): false|int|string
    {
        $key = [];

        foreach ($attributes as $attribute) {
            if (isset($activeRecord[$attribute]) || (is_object($activeRecord) && property_exists($activeRecord, $attribute))) {
                $key[] = $this->normalizeModelKey($activeRecord[$attribute]);
            }
        }

        if (count($key) > 1) {
            return serialize($key);
        }

        $key = reset($key);

        return is_scalar($key) ? $key : serialize($key);
    }

    /**
     * @param mixed $value raw key value.
     *
     * @return int|string|null normalized key value.
     */
    private function normalizeModelKey(mixed $value): int|string|null
    {
        if ($value instanceof Stringable) {
            /**
             * ensure matching to special objects, which are convertible to string, for cross-DBMS relations,
             * for example: `|MongoId`
             */
            $value = (string) $value;
        }

        return $value;
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

    /**
     * @return bool whether this query represents a relation to more than one record.
     *
     * This property is only used in relational context. If true, this relation will populate all query results into
     * active record instances using {@see Query::all()|all()}. If false, only the first row of the results will be
     * retrieved using {@see Query::one()|one()}.
     */
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
     * @return array the columns of the primary and foreign tables that establish a relation.
     *
     * The array keys must be columns of the table for this relation, and the array values must be the corresponding
     * columns from the primary table.
     *
     * Do not prefix or quote the column names as this will be done automatically by Yii. This property is only used in
     * relational context.
     */
    public function getLink(): array
    {
        return $this->link;
    }

    /**
     * @return ActiveQueryInterface|array|null the query associated with the junction table. Please call {@see (via)()} to
     * set this property instead of directly setting it.
     *
     * This property is only used in relational context.
     *
     * {@see via()}
     */
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
