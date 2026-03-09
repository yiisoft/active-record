<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Closure;
use InvalidArgumentException;
use ReflectionException;
use Throwable;
use Yiisoft\ActiveRecord\Internal\ArArrayHelper;
use Yiisoft\ActiveRecord\Internal\JoinsWithBuilder;
use Yiisoft\ActiveRecord\Internal\JunctionRowsFinder;
use Yiisoft\ActiveRecord\Internal\ModelRelationFilter;
use Yiisoft\ActiveRecord\Internal\RelationPopulator;
use Yiisoft\ActiveRecord\Internal\TableNameAndAliasResolver;
use Yiisoft\ActiveRecord\Internal\Typecaster;
use Yiisoft\ActiveRecord\Trait\RepositoryTrait;
use Yiisoft\Db\Command\CommandInterface;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Expression\ExpressionInterface;
use Yiisoft\Db\Query\BatchQueryResultInterface;
use Yiisoft\Db\Query\DataReaderInterface;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Query\QueryInterface;
use Yiisoft\Db\QueryBuilder\QueryBuilderInterface;
use Yiisoft\Definitions\Exception\CircularReferenceException;
use Yiisoft\Definitions\Exception\NotInstantiableException;

use function array_column;
use function array_combine;
use function array_flip;
use function array_intersect_key;
use function array_map;
use function array_values;
use function count;
use function is_array;
use function is_int;
use function is_object;
use function preg_match;
use function reset;
use function serialize;

/**
 * Represents a db query associated with an Active Record class.
 *
 * An ActiveQuery can be a normal query or be used in a relational context.
 *
 * ActiveQuery instances are usually created by {@see RepositoryTrait::findOne()},
 * {@see RepositoryTrait::findBySql()}, {@see RepositoryTrait::findAll()}.
 *
 * Relational queries are created by {@see ActiveRecordInterface::hasOne()} and {@see ActiveRecordInterface::hasMany()}.
 *
 * Normal Query
 * ------------
 *
 * ActiveQuery mainly provides the following methods to retrieve the query results:
 *
 * - {@see Query::one()}: returns a single record populated with the first row of data.
 * - {@see Query::all()}: returns all records based on the query results.
 * - {@see Query::count()}: returns the number of records.
 * - {@see Query::sum()}: returns the sum over the specified column.
 * - {@see Query::average()}: returns the average over the specified column.
 * - {@see Query::min()}: returns the min over the specified column.
 * - {@see Query::max()}: returns the max over the specified column.
 * - {@see Query::scalar()}: returns the value of the first column in the first row of the query result.
 * - {@see Query::column()}: returns the value of the first column in the query result.
 * - {@see Query::exists()}: returns a value indicating whether the query result has data or not.
 *
 * Because ActiveQuery extends from {@see Query}, one can use query methods, such as {@see Query::where()},
 * {@see Query::orderBy()} to customize the query options.
 *
 * ActiveQuery also provides the following more query options:
 *
 * - {@see ActiveQuery::with()}: list of relations that this query should be performed with.
 * - {@see ActiveQuery::joinWith()}: reuse a relation query definition to add a join to a query.
 * - {@see Query::indexBy()}: the name of the column by which the query result should be indexed.
 * - {@see ActiveQuery::asArray()}: whether to return each record as an array.
 *
 * These options can be configured using methods of the same name. For example:
 *
 * ```php
 * $customerQuery = Customer::query();
 * $query = $customerQuery->with('orders')->asArray()->all();
 * ```
 *
 * Relational query
 * ----------------
 *
 * In relational context, ActiveQuery represents a relation between two Active Record classes.
 *
 * Relational ActiveQuery instances are usually created by calling {@see ActiveRecordInterface::hasOne()} and
 * {@see ActiveRecordInterface::hasMany()}. An Active Record class declares a relation by defining a getter method which calls
 * one of the above methods and returns the created ActiveQuery object.
 *
 * A relation is specified by {@see ActiveQuery::link()} which represents the association between columns
 * of different tables; and the multiplicity of the relation is indicated by {@see ActiveQuery::multiple()}.
 *
 * If a relation involves a junction table, it may be specified by {@see ActiveQuery::via()}
 * or {@see ActiveQuery::viaTable()} method.
 *
 * These methods may only be called in a relational context. The same is true for
 * {@see ActiveQuery::inverseOf()}, which marks a relation as inverse of another relation
 * and {@see ActiveQuery::on()} which adds a condition that is to be added to relational
 * query join condition.
 *
 * @psalm-type ModelClass = ActiveRecordInterface|class-string<ActiveRecordInterface>
 * @psalm-import-type IndexBy from QueryInterface
 * @psalm-import-type Join from QueryInterface
 * @psalm-import-type ActiveQueryResult from ActiveQueryInterface
 * @psalm-import-type Via from ActiveQueryInterface
 *
 * @psalm-property IndexBy|null $indexBy
 * @psalm-suppress ClassMustBeFinal
 */
class ActiveQuery extends Query implements ActiveQueryInterface
{
    private ActiveRecordInterface $model;
    private ?string $sql = null;
    private array|ExpressionInterface|string|null $on = null;
    private ?bool $asArray = null;
    private array $with = [];
    private bool $multiple = false;
    private ?ActiveRecordInterface $primaryModel = null;
    /** @psalm-var array<string, string> */
    private array $link = [];

    /**
     * @psalm-var list<JoinWith>
     */
    private array $joinsWith = [];

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
     * @see ActiveQuery::inverseOf()
     */
    private ?string $inverseOf = null;

    /**
     * @var ActiveQueryInterface|array|null The relation associated with the junction table.
     * @psalm-var Via|null
     */
    private array|ActiveQueryInterface|null $via = null;

    /**
     * @psalm-param ModelClass $modelClass
     */
    final public function __construct(
        ActiveRecordInterface|string $modelClass,
    ) {
        $this->model = $modelClass instanceof ActiveRecordInterface
            ? $modelClass
            : new $modelClass();

        parent::__construct($this->model->db());
    }

    /**
     * Clones internal objects
     */
    public function __clone()
    {
        /// Make a clone of "via" object so that the same query object can be reused multiple times.
        if (is_object($this->via)) {
            $this->via = clone $this->via;
        } elseif (is_array($this->via)) {
            $this->via = [$this->via[0], clone $this->via[1], $this->via[2]];
        }
    }

    public function asArray(?bool $value = true): static
    {
        $this->asArray = $value;
        return $this;
    }

    public function isAsArray(): ?bool
    {
        return $this->asArray;
    }

    public function with(array|string ...$with): static
    {
        if (isset($with[0]) && is_array($with[0])) {
            /// the parameter is given as an array
            $with = $with[0];
        }

        if (empty($this->with)) {
            $this->with = $with;
        } elseif (!empty($with)) {
            foreach ($with as $name => $value) {
                if (is_int($name)) {
                    // repeating relation is fine as `normalizeRelations()` handle it well
                    $this->with[] = $value;
                } else {
                    $this->with[$name] = $value;
                }
            }
        }

        return $this;
    }

    public function getWith(): array
    {
        return $this->with;
    }

    public function each(): DataReaderInterface
    {
        /** @psalm-suppress InvalidArgument */
        return $this->createCommand()
            ->query()
            ->indexBy($this->indexBy)
            ->resultCallback($this->populateOne(...));
    }

    /**
     * @throws CircularReferenceException
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotInstantiableException
     * @throws Throwable
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
     */
    public function prepare(QueryBuilderInterface $builder): QueryInterface
    {
        /**
         * NOTE: Because the same ActiveQuery may be used to build different SQL statements, one for count query, the
         * other for row data query, it is important to make sure the same ActiveQuery can be used to build SQL
         * statements many times.
         */
        if (!empty($this->joinsWith)) {
            JoinsWithBuilder::build($this);
            /**
             * Clean it up to avoid issue @link https://github.com/yiisoft/yii2/issues/2687
             */
            $this->joinsWith = [];
        }

        if (empty($this->getFrom())) {
            $this->from = [$this->getPrimaryTableName()];
        }

        if (empty($this->getSelect()) && !empty($this->getJoins())) {
            [, $alias] = TableNameAndAliasResolver::resolve($this);

            $this->select(["$alias.*"]);
        }

        if ($this->primaryModel === null) {
            $query = $this->createInstance();
        } else {
            $where = $this->getWhere();

            if ($this->via instanceof ActiveQueryInterface) {
                $viaModels = JunctionRowsFinder::find($this->via, [$this->primaryModel]);
                ModelRelationFilter::apply($this, $viaModels);
            } elseif (is_array($this->via)) {
                [$viaName, $viaQuery, $viaCallableUsed] = $this->via;

                if ($viaQuery->isMultiple()) {
                    if ($viaCallableUsed) {
                        $viaModels = $viaQuery->all();
                    } elseif ($this->primaryModel->isRelationPopulated($viaName)) {
                        /** @var ActiveRecordInterface[]|array[] $viaModels */
                        $viaModels = $this->primaryModel->relation($viaName);
                    } else {
                        $viaModels = $viaQuery->all();
                        $this->primaryModel->populateRelation($viaName, $viaModels);
                    }
                } else {
                    if ($viaCallableUsed) {
                        $model = $viaQuery->one();
                    } elseif ($this->primaryModel->isRelationPopulated($viaName)) {
                        $model = $this->primaryModel->relation($viaName);
                    } else {
                        $model = $viaQuery->one();
                        $this->primaryModel->populateRelation($viaName, $model);
                    }
                    $viaModels = $model === null ? [] : [$model];
                }
                ModelRelationFilter::apply($this, $viaModels);
            } else {
                ModelRelationFilter::apply($this, [$this->primaryModel]);
            }

            $query = $this->createInstance();
            $this->setWhere($where);
        }

        if (!empty($this->on)) {
            $query->andWhere($this->on);
        }

        return $query;
    }

    public function populate(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        if (!empty($this->joins) && $this->indexBy === null) {
            $rows = $this->removeDuplicatedRows($rows);
        }

        $models = $this->createModels($rows);

        if (!empty($this->with)) {
            $this->findWith($this->with, $models);
        }

        $this->addInverseRelations($models);

        return $models;
    }

    public function one(): array|ActiveRecordInterface|null
    {
        if ($this->shouldEmulateExecution()) {
            return null;
        }

        $row = $this->createCommand()->queryOne();
        if ($row === null) {
            return null;
        }

        return $this->populateOne($row);
    }

    /**
     * Creates a db command that can be used to execute this query.
     *
     * @throws Exception
     */
    public function createCommand(): CommandInterface
    {
        if ($this->sql === null) {
            [$sql, $params] = $this->db->getQueryBuilder()->build($this);
        } else {
            $sql = $this->sql;
            $params = $this->params;
        }

        return $this->db->createCommand($sql, $params);
    }

    public function joinWith(
        array|string $with,
        array|bool $eagerLoading = true,
        array|string $joinType = 'LEFT JOIN',
    ): static {
        $relations = [];

        foreach ((array) $with as $name => $callback) {
            if (is_int($name)) {
                $name = $callback;
                $callback = null;
            }
            /** @var string $name */

            if (preg_match('/^(.*?)(?:\s+AS\s+|\s+)(\w+)$/i', $name, $matches)) {
                /** The relation is defined with an alias, adjust callback to apply alias */
                [, $relation, $alias] = $matches;

                $name = $relation;

                $callback = static function (ActiveQueryInterface $query) use ($callback, $alias): void {
                    $query->alias($alias);

                    if ($callback !== null) {
                        $callback($query);
                    }
                };
            }

            if ($callback === null) {
                $relations[] = $name;
            } else {
                $relations[$name] = $callback;
            }
        }

        $this->joinsWith[] = new JoinWith($relations, $eagerLoading, $joinType);

        return $this;
    }

    public function resetJoinsWith(): void
    {
        $this->joinsWith = [];
    }

    public function resetWith(): static
    {
        $this->with = [];
        $this->joinsWith = array_map(
            static fn(JoinWith $joinWith) => $joinWith->withoutEagerLoading(),
            $this->joinsWith,
        );
        return $this;
    }

    public function innerJoinWith(array|string $with, array|bool $eagerLoading = true): static
    {
        return $this->joinWith($with, $eagerLoading, 'INNER JOIN');
    }

    public function on(array|ExpressionInterface|string $condition, array $params = []): static
    {
        $this->on = $condition;
        $this->addParams($params);
        return $this;
    }

    public function andOn(array|ExpressionInterface|string $condition, array $params = []): static
    {
        $this->on = $this->on === null
            ? $condition
            : ['and', $this->on, $condition];
        $this->addParams($params);
        return $this;
    }

    public function orOn(array|ExpressionInterface|string $condition, array $params = []): static
    {
        $this->on = $this->on === null
            ? $condition
            : ['or', $this->on, $condition];
        $this->addParams($params);
        return $this;
    }

    public function viaTable(string $tableName, array $link, ?callable $callable = null): static
    {
        $model = $this->primaryModel ?? $this->model;

        $relation = (new static($model))
            ->from([$tableName])
            ->link($link)
            ->multiple(true)
            ->asArray();

        $this->via = $relation;

        if ($callable !== null) {
            $callable($relation);
        }

        return $this;
    }

    public function alias(string $alias): static
    {
        if (count($this->from) < 2) {
            [$tableName] = TableNameAndAliasResolver::resolve($this);
            $this->from = [$alias => $tableName];
        } else {
            $tableName = $this->getPrimaryTableName();

            foreach ($this->from as $key => $table) {
                if ($table === $tableName) {
                    unset($this->from[$key]);
                    $this->from[$alias] = $tableName;
                }
            }
        }

        return $this;
    }

    public function getTablesUsedInFrom(): array
    {
        if (empty($this->from)) {
            return $this->db->getQuoter()->cleanUpTableNames([$this->getPrimaryTableName()]);
        }

        return parent::getTablesUsedInFrom();
    }

    public function getOn(): array|ExpressionInterface|string|null
    {
        return $this->on;
    }

    public function getJoinsWith(): array
    {
        return $this->joinsWith;
    }

    public function getSql(): ?string
    {
        return $this->sql;
    }

    public function findByPk(array|float|int|string $values): array|ActiveRecordInterface|null
    {
        $values = (array) $values;

        $model = $this->getModel();
        $primaryKey = $model->primaryKey();

        if (empty($primaryKey)) {
            throw new InvalidConfigException($model::class . ' must have a primary key.');
        }

        if (count($primaryKey) !== count($values)) {
            throw new InvalidArgumentException(
                'The primary key has ' . count($primaryKey) . ' columns, but ' . count($values) . ' values are passed.',
            );
        }

        if (!empty($this->getJoins()) || !empty($this->getJoinsWith())) {
            $tableName = $model->tableName();

            foreach ($primaryKey as &$pk) {
                $pk = "$tableName.$pk";
            }
        }

        return (clone $this)->andWhere(array_combine($primaryKey, $values))->one();
    }

    public function sql(?string $value): static
    {
        $this->sql = $value;
        return $this;
    }

    public function getModel(): ActiveRecordInterface
    {
        return clone $this->model;
    }

    public function batch(int $batchSize = 100): BatchQueryResultInterface
    {
        /**
         * @var Closure(non-empty-array<array>):non-empty-array<object> $callback
         */
        $callback = $this->index(...);
        return parent::batch($batchSize)->indexBy(null)->resultCallback($callback);
    }

    public function via(string $relationName, ?callable $callable = null): static
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

    public function resetVia(): static
    {
        $this->via = null;
        return $this;
    }

    public function inverseOf(string $relationName): static
    {
        $this->inverseOf = $relationName;
        return $this;
    }

    public function getInverseOf(): ?string
    {
        return $this->inverseOf;
    }

    public function relatedRecords(): ActiveRecordInterface|array|null
    {
        return $this->multiple ? $this->all() : $this->one();
    }

    public function populateRelation(string $name, array &$primaryModels): array
    {
        return RelationPopulator::populate($this, $name, $primaryModels);
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function getPrimaryModel(): ?ActiveRecordInterface
    {
        return $this->primaryModel;
    }

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

    public function primaryModel(?ActiveRecordInterface $value): static
    {
        $this->primaryModel = $value;

        return $this;
    }

    public function link(array $value): static
    {
        $this->link = $value;
        return $this;
    }

    /**
     * Queries a scalar value by setting {@see Query::select()} first.
     *
     * Restores the value of select to make this query reusable.
     *
     * @param ExpressionInterface|string $selectExpression The expression to be selected.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws Throwable
     */
    protected function queryScalar(string|ExpressionInterface $selectExpression): bool|string|int|float|null
    {
        if ($this->sql === null) {
            return parent::queryScalar($selectExpression);
        }

        $command = (new Query($this->db))->select([$selectExpression])
            ->from(['c' => "($this->sql)"])
            ->params($this->params)
            ->createCommand();

        return $command->queryScalar();
    }

    protected function getPrimaryTableName(): string
    {
        return $this->getModel()->tableName();
    }

    protected function index(array $rows): array
    {
        return ArArrayHelper::index($this->populate($rows), $this->indexBy);
    }

    /**
     * Converts found rows into model instances.
     *
     * @param array[] $rows The rows to be converted.
     *
     * @return ActiveRecordInterface[]|array[] The model instances.
     *
     * @psalm-param non-empty-list<array<string, mixed>> $rows
     * @psalm-return non-empty-list<ActiveQueryResult>
     */
    protected function createModels(array $rows): array
    {
        if ($this->asArray) {
            $model = $this->getModel();
            return array_map(
                static fn(array $row) => Typecaster::cast($row, $model),
                $rows,
            );
        }

        if ($this->resultCallback !== null) {
            $rows = ($this->resultCallback)($rows);

            if ($rows[0] instanceof ActiveRecordInterface) {
                /** @psalm-var non-empty-list<ActiveRecordInterface> */
                return $rows;
            }
        }
        /** @var non-empty-list<array<string, mixed>> $rows */

        return array_map(
            fn(array $row) => $this->getModel()->populateRecord($row),
            $rows,
        );
    }

    /**
     * Removes duplicated rows by checking their primary key values.
     *
     * This method is mainly called when a join query is performed, which may cause duplicated rows being returned.
     *
     * @param array[] $rows The rows to be checked.
     *
     * @throws Exception
     * @throws InvalidConfigException
     *
     * @return array[] The distinctive rows.
     *
     * @psalm-param non-empty-list<array<string, mixed>> $rows
     * @psalm-return non-empty-list<array<string, mixed>>
     */
    private function removeDuplicatedRows(array $rows): array
    {
        $model = $this->getModel();
        $pks = $model->primaryKey();

        if (empty($pks)) {
            throw new InvalidConfigException('Primary key of "' . $model::class . '" can not be empty.');
        }

        foreach ($pks as $pk) {
            if (!isset($rows[0][$pk])) {
                return $rows;
            }
        }

        if (count($pks) === 1) {
            /** @psalm-var non-empty-list<string|int> $hash */
            $hash = array_column($rows, reset($pks));
        } else {
            $flippedPks = array_flip($pks);
            $hash = array_map(
                static fn(array $row): string => serialize(array_intersect_key($row, $flippedPks)),
                $rows,
            );
        }

        return array_values(array_combine($hash, $rows));
    }

    private function createInstance(): static
    {
        return (new static($this->model))
            ->where($this->getWhere())
            ->limit($this->getLimit())
            ->offset($this->getOffset())
            ->orderBy($this->getOrderBy())
            ->indexBy($this->getIndexBy())
            ->select($this->select)
            ->selectOption($this->selectOption)
            ->distinct($this->distinct)
            ->from($this->from)
            ->groupBy($this->groupBy)
            ->setJoins($this->joins)
            ->having($this->having)
            ->setUnions($this->union)
            ->params($this->params)
            ->withQueries(...$this->withQueries);
    }

    /**
     * @psalm-param array<string, mixed> $row
     * @psalm-return ActiveQueryResult
     */
    private function populateOne(array $row): ActiveRecordInterface|array
    {
        return $this->populate([$row])[0];
    }

    /**
     * Finds records corresponding to one or multiple relations and populates them into the primary models.
     *
     * @param array $with a list of relations that this query should be performed with. Please refer
     * to {@see ActiveQuery::with()} for details about specifying this parameter.
     * @param ActiveRecordInterface[]|array[] $models the primary models (can be either AR instances or arrays)
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotSupportedException
     * @throws ReflectionException
     * @throws Throwable
     *
     * @psalm-param non-empty-list<ActiveQueryResult> $models
     * @psalm-param-out non-empty-list<ActiveQueryResult> $models
     */
    private function findWith(array $with, array &$models): void
    {
        $primaryModel = reset($models);

        if (!$primaryModel instanceof ActiveRecordInterface) {
            $primaryModel = $this->getModel();
        }

        $relations = $this->normalizeRelations($primaryModel, $with);

        foreach ($relations as $name => $relation) {
            if ($relation->isAsArray() === null) {
                // inherit asArray from a primary query
                $relation->asArray($this->asArray);
            }

            $relation->populateRelation($name, $models);
        }
    }

    /**
     * @psalm-return array<string, ActiveQueryInterface>
     */
    private function normalizeRelations(ActiveRecordInterface $model, array $with): array
    {
        $relations = [];

        foreach ($with as $name => $callback) {
            if (is_int($name)) {
                $name = $callback;
                $callback = null;
            }
            /**
             * @var string $name
             * @var Closure|null $callback
             */

            if (($pos = strpos($name, '.')) !== false) {
                // with sub-relations
                $childName = substr($name, $pos + 1);
                $name = substr($name, 0, $pos);
            } else {
                $childName = null;
            }

            if (!isset($relations[$name])) {
                $relation = $model->relationQuery($name);
                $relation->primaryModel(null);
                $relations[$name] = $relation;
            } else {
                $relation = $relations[$name];
            }

            if (isset($childName)) {
                $relation->with([$childName => $callback]);
            } elseif ($callback !== null) {
                $callback($relation);
            }
        }

        return $relations;
    }

    /**
     * If applicable, populate the query's primary model into the related records' inverse relationship.
     *
     * @param ActiveRecordInterface[]|array[] $result the array of related records as generated
     * by {@see ActiveQuery::populate()}
     *
     * @throws InvalidConfigException
     *
     * @psalm-param non-empty-list<ActiveQueryResult> $result
     * @psalm-param-out non-empty-list<ActiveQueryResult> $result
     */
    private function addInverseRelations(array &$result): void
    {
        if ($this->inverseOf === null) {
            return;
        }

        $relatedModel = reset($result);

        if ($relatedModel instanceof ActiveRecordInterface) {
            $inverseRelation = $relatedModel->relationQuery($this->inverseOf);
            $primaryModel = $inverseRelation->isMultiple() ? [$this->primaryModel] : $this->primaryModel;

            /** @var ActiveRecordInterface $relatedModel */
            foreach ($result as $relatedModel) {
                $relatedModel->populateRelation($this->inverseOf, $primaryModel);
            }
        } else {
            $inverseRelation = $this->getModel()->relationQuery($this->inverseOf);
            $primaryModel = $inverseRelation->isMultiple() ? [$this->primaryModel] : $this->primaryModel;

            /** @var array $relatedModel */
            foreach ($result as &$relatedModel) {
                $relatedModel[$this->inverseOf] = $primaryModel;
            }
        }
    }
}
