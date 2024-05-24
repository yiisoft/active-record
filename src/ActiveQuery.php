<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Closure;
use ReflectionException;
use Throwable;
use Yiisoft\Db\Command\CommandInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Expression\ExpressionInterface;
use Yiisoft\Db\Helper\DbArrayHelper;
use Yiisoft\Db\Query\BatchQueryResultInterface;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Query\QueryInterface;
use Yiisoft\Db\QueryBuilder\QueryBuilderInterface;
use Yiisoft\Definitions\Exception\CircularReferenceException;
use Yiisoft\Definitions\Exception\NotInstantiableException;
use Yiisoft\Factory\NotFoundException;

use function array_column;
use function array_combine;
use function array_flip;
use function array_intersect_key;
use function array_map;
use function array_merge;
use function array_values;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function preg_match;
use function reset;
use function serialize;
use function strpos;
use function substr;

/**
 * Represents a db query associated with an Active Record class.
 *
 * An ActiveQuery can be a normal query or be used in a relational context.
 *
 * ActiveQuery instances are usually created by {@see findOne()}, {@see findBySql()}, {@see findAll()}.
 *
 * Relational queries are created by {@see ActiveRecord::hasOne()} and {@see ActiveRecord::hasMany()}.
 *
 * Normal Query
 * ------------
 *
 * ActiveQuery mainly provides the following methods to retrieve the query results:
 *
 * - {@see one()}: returns a single record populated with the first row of data.
 * - {@see all()}: returns all records based on the query results.
 * - {@see count()}: returns the number of records.
 * - {@see sum()}: returns the sum over the specified column.
 * - {@see average()}: returns the average over the specified column.
 * - {@see min()}: returns the min over the specified column.
 * - {@see max()}: returns the max over the specified column.
 * - {@see scalar()}: returns the value of the first column in the first row of the query result.
 * - {@see column()}: returns the value of the first column in the query result.
 * - {@see exists()}: returns a value indicating whether the query result has data or not.
 *
 * Because ActiveQuery extends from {@see Query}, one can use query methods, such as {@see where()}, {@see orderBy()} to
 * customize the query options.
 *
 * ActiveQuery also provides the following more query options:
 *
 * - {@see with()}: list of relations that this query should be performed with.
 * - {@see joinWith()}: reuse a relation query definition to add a join to a query.
 * - {@see indexBy()}: the name of the column by which the query result should be indexed.
 * - {@see asArray()}: whether to return each record as an array.
 *
 * These options can be configured using methods of the same name. For example:
 *
 * ```php
 * $customerQuery = new ActiveQuery(Customer::class, $db);
 * $query = $customerQuery->with('orders')->asArray()->all();
 * ```
 *
 * Relational query
 * ----------------
 *
 * In relational context ActiveQuery represents a relation between two Active Record classes.
 *
 * Relational ActiveQuery instances are usually created by calling {@see ActiveRecord::hasOne()} and
 * {@see ActiveRecord::hasMany()}. An Active Record class declares a relation by defining a getter method which calls
 * one of the above methods and returns the created ActiveQuery object.
 *
 * A relation is specified by {@see link()} which represents the association between columns of different tables; and
 * the multiplicity of the relation is indicated by {@see multiple()}.
 *
 * If a relation involves a junction table, it may be specified by {@see via()} or {@see viaTable()} method.
 *
 * These methods may only be called in a relational context. Same is true for {@see inverseOf()}, which marks a relation
 * as inverse of another relation and {@see onCondition()} which adds a condition that's to be added to relational
 * query join condition.
 */
class ActiveQuery extends Query implements ActiveQueryInterface
{
    use ActiveQueryTrait;
    use ActiveRelationTrait;

    private string|null $sql = null;
    private array|string|null $on = null;
    private array $joinWith = [];
    private ActiveRecordInterface|null $arInstance = null;

    /**
     * @psalm-param class-string<ActiveRecordInterface> $arClass
     */
    final public function __construct(
        protected string $arClass,
        protected ConnectionInterface $db,
        private ActiveRecordFactory|null $arFactory = null,
        private string $tableName = ''
    ) {
        parent::__construct($db);
    }

    /**
     * Executes a query and returns all results as an array.
     *
     * If null, the db connection returned by {@see arClass} will be used.
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     *
     * @psalm-suppress ImplementedReturnTypeMismatch
     * @return ActiveRecordInterface[] The query results. If the query results in nothing, an empty array will be returned.
     */
    public function all(): array
    {
        if ($this->shouldEmulateExecution()) {
            return [];
        }

        return $this->populate($this->createCommand()->queryAll(), $this->indexBy);
    }

    public function batch(int $batchSize = 100): BatchQueryResultInterface
    {
        return parent::batch($batchSize)->setPopulatedMethod(
            fn (array $rows, null|Closure|string $indexBy) => $this->populate($rows, $indexBy)
        );
    }

    public function each(int $batchSize = 100): BatchQueryResultInterface
    {
        return parent::each($batchSize)->setPopulatedMethod(
            fn (array $rows, null|Closure|string $indexBy) => $this->populate($rows, $indexBy)
        );
    }

    /**
     * @throws CircularReferenceException
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotFoundException
     * @throws NotInstantiableException
     * @throws Throwable
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
     */
    public function prepare(QueryBuilderInterface $builder): QueryInterface
    {
        /**
         * NOTE: Because the same ActiveQuery may be used to build different SQL statements, one for count query, the
         * other for row data query, it's important to make sure the same ActiveQuery can be used to build SQL
         * statements many times.
         */
        if (!empty($this->joinWith)) {
            $this->buildJoinWith();
            /**
             * Clean it up to avoid issue @link https://github.com/yiisoft/yii2/issues/2687
             */
            $this->joinWith = [];
        }

        if (empty($this->getFrom())) {
            $this->from = [$this->getPrimaryTableName()];
        }

        if (empty($this->getSelect()) && !empty($this->getJoins())) {
            [, $alias] = $this->getTableNameAndAlias();

            $this->select(["$alias.*"]);
        }

        if ($this->primaryModel === null) {
            $query = $this->createInstance();
        } else {
            $where = $this->getWhere();

            if ($this->via instanceof self) {
                /** via junction table */
                $viaModels = $this->via->findJunctionRows([$this->primaryModel]);

                $this->filterByModels($viaModels);
            } elseif (is_array($this->via)) {
                [$viaName, $viaQuery, $viaCallableUsed] = $this->via;

                if ($viaQuery->getMultiple()) {
                    if ($viaCallableUsed) {
                        $viaModels = $viaQuery->all();
                    } elseif ($this->primaryModel->isRelationPopulated($viaName)) {
                        $viaModels = $this->primaryModel->relation($viaName);
                    } else {
                        $viaModels = $viaQuery->all();
                        $this->primaryModel->populateRelation($viaName, $viaModels);
                    }
                } else {
                    if ($viaCallableUsed) {
                        $model = $viaQuery->onePopulate();
                    } elseif ($this->primaryModel->isRelationPopulated($viaName)) {
                        $model = $this->primaryModel->relation($viaName);
                    } else {
                        $model = $viaQuery->onePopulate();
                        $this->primaryModel->populateRelation($viaName, $model);
                    }
                    $viaModels = $model === null ? [] : [$model];
                }
                $this->filterByModels($viaModels);
            } else {
                $this->filterByModels([$this->primaryModel]);
            }

            $query = $this->createInstance();
            $this->where($where);
        }

        if (!empty($this->on)) {
            $query->andWhere($this->on);
        }

        return $query;
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws ReflectionException
     * @throws Throwable
     */
    public function populate(array $rows, Closure|string|null $indexBy = null): array
    {
        if (empty($rows)) {
            return [];
        }

        $models = $this->createModels($rows);

        if (empty($models)) {
            return [];
        }

        if (!empty($this->join) && $this->getIndexBy() === null) {
            $models = $this->removeDuplicatedModels($models);
        }

        if (!empty($this->with)) {
            $this->findWith($this->with, $models);
        }

        if ($this->inverseOf !== null) {
            $this->addInverseRelations($models);
        }

        return ArArrayHelper::populate($models, $indexBy);
    }

    /**
     * Removes duplicated models by checking their primary key values.
     *
     * This method is mainly called when a join query is performed, which may cause duplicated rows being returned.
     *
     * @param array $models The models to be checked.
     *
     * @throws CircularReferenceException
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotFoundException
     * @throws NotInstantiableException
     *
     * @return array The distinctive models.
     */
    private function removeDuplicatedModels(array $models): array
    {
        $model = reset($models);

        if ($this->asArray) {
            $pks = $this->getARInstance()->primaryKey();

            if (empty($pks)) {
                throw new InvalidConfigException("Primary key of '$this->arClass' can not be empty.");
            }

            foreach ($pks as $pk) {
                if (!isset($model[$pk])) {
                    return $models;
                }
            }

            if (count($pks) === 1) {
                $hash = array_column($models, reset($pks));
            } else {
                $flippedPks = array_flip($pks);
                $hash = array_map(static fn ($model) => serialize(array_intersect_key($model, $flippedPks)), $models);
            }
        } else {
            $pks = $model->getPrimaryKey(true);

            if (empty($pks)) {
                throw new InvalidConfigException("Primary key of '$this->arClass' can not be empty.");
            }

            foreach ($pks as $pk) {
                if ($pk === null) {
                    return $models;
                }
            }

            if (count($pks) === 1) {
                $hash = array_map(static fn ($model) => $model->getPrimaryKey(), $models);
            } else {
                $hash = array_map(static fn ($model) => serialize($model->getPrimaryKey(true)), $models);
            }
        }

        return array_values(array_combine($hash, $models));
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws ReflectionException
     * @throws Throwable
     */
    public function allPopulate(): array
    {
        $rows = $this->all();

        if ($rows !== []) {
            $rows = $this->populate($rows, $this->indexBy);
        }

        return $rows;
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws ReflectionException
     * @throws Throwable
     */
    public function onePopulate(): array|ActiveRecordInterface|null
    {
        $row = $this->one();

        if ($row !== null) {
            $activeRecord = $this->populate([$row], $this->indexBy);
            $row = reset($activeRecord) ?: null;
        }

        return $row;
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

    /**
     * Queries a scalar value by setting {@see select()} first.
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
    protected function queryScalar(string|ExpressionInterface $selectExpression): bool|string|null|int|float
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

    public function joinWith(
        array|string $with,
        array|bool $eagerLoading = true,
        array|string $joinType = 'LEFT JOIN'
    ): self {
        $relations = [];

        foreach ((array) $with as $name => $callback) {
            if (is_int($name)) {
                $name = $callback;
                $callback = null;
            }

            if (preg_match('/^(.*?)(?:\s+AS\s+|\s+)(\w+)$/i', $name, $matches)) {
                /** The relation is defined with an alias, adjust callback to apply alias */
                [, $relation, $alias] = $matches;

                $name = $relation;

                $callback = static function (self $query) use ($callback, $alias): void {
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

        $this->joinWith[] = [$relations, $eagerLoading, $joinType];

        return $this;
    }

    public function resetJoinWith(): void
    {
        $this->joinWith = [];
    }

    /**
     * @throws CircularReferenceException
     * @throws InvalidConfigException
     * @throws NotFoundException
     * @throws NotInstantiableException
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
     */
    public function buildJoinWith(): void
    {
        $join = $this->join;

        $this->join = [];

        $arClass = $this->getARInstance();

        foreach ($this->joinWith as [$with, $eagerLoading, $joinType]) {
            $this->joinWithRelations($arClass, $with, $joinType);

            if (is_array($eagerLoading)) {
                foreach ($with as $name => $callback) {
                    if (is_int($name)) {
                        if (!in_array($callback, $eagerLoading, true)) {
                            unset($with[$name]);
                        }
                    } elseif (!in_array($name, $eagerLoading, true)) {
                        unset($with[$name]);
                    }
                }
            } elseif (!$eagerLoading) {
                $with = [];
            }

            $this->with($with);
        }

        /**
         * Remove duplicated joins added by joinWithRelations that may be added, for example, when joining a relation
         * and a via relation at the same time.
         */
        $uniqueJoins = [];

        foreach ($this->join as $j) {
            $uniqueJoins[serialize($j)] = $j;
        }
        $this->join = array_values($uniqueJoins);

        /**
         * @link https://github.com/yiisoft/yii2/issues/16092
         */
        $uniqueJoinsByTableName = [];

        foreach ($this->join as $config) {
            $tableName = serialize($config[1]);
            if (!array_key_exists($tableName, $uniqueJoinsByTableName)) {
                $uniqueJoinsByTableName[$tableName] = $config;
            }
        }

        $this->join = array_values($uniqueJoinsByTableName);

        if (!empty($join)) {
            /**
             * Append explicit join to {@see joinWith()} {@link https://github.com/yiisoft/yii2/issues/2880}
             */
            $this->join = empty($this->join) ? $join : array_merge($this->join, $join);
        }
    }

    public function innerJoinWith(array|string $with, array|bool $eagerLoading = true): self
    {
        return $this->joinWith($with, $eagerLoading, 'INNER JOIN');
    }

    /**
     * Modifies the current query by adding join fragments based on the given relations.
     *
     * @param ActiveRecordInterface $arClass The primary model.
     * @param array $with The relations to be joined.
     * @param array|string $joinType The join type.
     *
     * @throws CircularReferenceException
     * @throws InvalidConfigException
     * @throws NotFoundException
     * @throws NotInstantiableException
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
     */
    private function joinWithRelations(ActiveRecordInterface $arClass, array $with, array|string $joinType): void
    {
        $relations = [];

        foreach ($with as $name => $callback) {
            if (is_int($name)) {
                $name = $callback;
                $callback = null;
            }

            $primaryModel = $arClass;
            $parent = $this;
            $prefix = '';

            while (($pos = strpos($name, '.')) !== false) {
                $childName = substr($name, $pos + 1);
                $name = substr($name, 0, $pos);
                $fullName = $prefix === '' ? $name : "$prefix.$name";

                if (!isset($relations[$fullName])) {
                    $relations[$fullName] = $relation = $primaryModel->relationQuery($name);
                    if ($relation instanceof ActiveQueryInterface) {
                        $this->joinWithRelation($parent, $relation, $this->getJoinType($joinType, $fullName));
                    }
                } else {
                    $relation = $relations[$fullName];
                }

                if ($relation instanceof ActiveQueryInterface) {
                    $primaryModel = $relation->getARInstance();
                    $parent = $relation;
                }

                $prefix = $fullName;
                $name = $childName;
            }

            $fullName = $prefix === '' ? $name : "$prefix.$name";

            if (!isset($relations[$fullName])) {
                $relations[$fullName] = $relation = $primaryModel->relationQuery($name);

                if ($callback !== null) {
                    $callback($relation);
                }

                if ($relation instanceof ActiveQueryInterface && !empty($relation->getJoinWith())) {
                    $relation->buildJoinWith();
                }

                if ($relation instanceof ActiveQueryInterface) {
                    $this->joinWithRelation($parent, $relation, $this->getJoinType($joinType, $fullName));
                }
            }
        }
    }

    /**
     * Returns the join type based on the given join type parameter and the relation name.
     *
     * @param array|string $joinType The given join type(s).
     * @param string $name The relation name.
     *
     * @return string The real join type.
     */
    private function getJoinType(array|string $joinType, string $name): string
    {
        if (is_array($joinType) && isset($joinType[$name])) {
            return $joinType[$name];
        }

        return is_string($joinType) ? $joinType : 'INNER JOIN';
    }

    /**
     * Returns the table name and the table alias for {@see arClass}.
     *
     * @throws CircularReferenceException
     * @throws InvalidConfigException
     * @throws NotFoundException
     * @throws NotInstantiableException
     */
    private function getTableNameAndAlias(): array
    {
        if (empty($this->from)) {
            $tableName = $this->getPrimaryTableName();
        } else {
            $tableName = '';

            foreach ($this->from as $alias => $tableName) {
                if (is_string($alias)) {
                    return [$tableName, $alias];
                }
                break;
            }
        }

        if (preg_match('/^(.*?)\s+({{\w+}}|\w+)$/', $tableName, $matches)) {
            $alias = $matches[2];
        } else {
            $alias = $tableName;
        }

        return [$tableName, $alias];
    }

    /**
     * Joins a parent query with a child query.
     *
     * The current query object will be modified so.
     *
     * @param ActiveQueryInterface $parent The parent query.
     * @param ActiveQueryInterface $child The child query.
     * @param string $joinType The join type.
     *
     * @throws CircularReferenceException
     * @throws NotFoundException
     * @throws NotInstantiableException
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
     */
    private function joinWithRelation(ActiveQueryInterface $parent, ActiveQueryInterface $child, string $joinType): void
    {
        $via = $child->getVia();
        /** @var ActiveQuery $child */
        $child->via = null;

        if ($via instanceof self) {
            // via table
            $this->joinWithRelation($parent, $via, $joinType);
            $this->joinWithRelation($via, $child, $joinType);

            return;
        }

        if (is_array($via)) {
            // via relation
            $this->joinWithRelation($parent, $via[1], $joinType);
            $this->joinWithRelation($via[1], $child, $joinType);

            return;
        }

        /** @var ActiveQuery $parent */
        [$parentTable, $parentAlias] = $parent->getTableNameAndAlias();
        [$childTable, $childAlias] = $child->getTableNameAndAlias();

        if (!empty($child->getLink())) {
            if (!str_contains($parentAlias, '{{')) {
                $parentAlias = '{{' . $parentAlias . '}}';
            }

            if (!str_contains($childAlias, '{{')) {
                $childAlias = '{{' . $childAlias . '}}';
            }

            $on = [];

            foreach ($child->getLink() as $childColumn => $parentColumn) {
                $on[] = "$parentAlias.[[$parentColumn]] = $childAlias.[[$childColumn]]";
            }

            $on = implode(' AND ', $on);

            if (!empty($child->getOn())) {
                $on = ['and', $on, $child->getOn()];
            }
        } else {
            $on = $child->getOn();
        }

        $this->join($joinType, empty($child->getFrom()) ? $childTable : $child->getFrom(), $on ?? '');

        $where = $child->getWhere();

        if (!empty($where)) {
            $this->andWhere($where);
        }

        $having = $child->getHaving();

        if (!empty($having)) {
            $this->andHaving($having);
        }

        if (!empty($child->getOrderBy())) {
            $this->addOrderBy($child->getOrderBy());
        }

        if (!empty($child->getGroupBy())) {
            $this->addGroupBy($child->getGroupBy());
        }

        if (!empty($child->getParams())) {
            $this->addParams($child->getParams());
        }

        if (!empty($child->getJoins())) {
            foreach ($child->getJoins() as $join) {
                $this->join[] = $join;
            }
        }

        if (!empty($child->getUnions())) {
            foreach ($child->getUnions() as $union) {
                $this->union[] = $union;
            }
        }
    }

    public function onCondition(array|string $condition, array $params = []): self
    {
        $this->on = $condition;

        $this->addParams($params);

        return $this;
    }

    public function andOnCondition(array|string $condition, array $params = []): self
    {
        if ($this->on === null) {
            $this->on = $condition;
        } else {
            $this->on = ['and', $this->on, $condition];
        }

        $this->addParams($params);

        return $this;
    }

    public function orOnCondition(array|string $condition, array $params = []): self
    {
        if ($this->on === null) {
            $this->on = $condition;
        } else {
            $this->on = ['or', $this->on, $condition];
        }

        $this->addParams($params);

        return $this;
    }

    public function viaTable(string $tableName, array $link, callable $callable = null): self
    {
        $arClass = $this->primaryModel ? $this->primaryModel::class : $this->arClass;
        $arClassInstance = new self($arClass, $this->db);

        /** @psalm-suppress UndefinedMethod */
        $relation = $arClassInstance->from([$tableName])->link($link)->multiple(true)->asArray();

        $this->via = $relation;

        if ($callable !== null) {
            $callable($relation);
        }

        return $this;
    }

    public function alias(string $alias): self
    {
        if (empty($this->from) || count($this->from) < 2) {
            [$tableName] = $this->getTableNameAndAlias();
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

    /**
     * @throws CircularReferenceException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws NotInstantiableException
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
     */
    public function getTablesUsedInFrom(): array
    {
        if (empty($this->from)) {
            return $this->db->getQuoter()->cleanUpTableNames([$this->getPrimaryTableName()]);
        }

        return parent::getTablesUsedInFrom();
    }

    /**
     * @throws CircularReferenceException
     * @throws NotFoundException
     * @throws NotInstantiableException
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
     */
    protected function getPrimaryTableName(): string
    {
        return $this->getARInstance()->getTableName();
    }

    public function getOn(): array|string|null
    {
        return $this->on;
    }

    /**
     * @return array $value A list of relations that this query should be joined with.
     */
    public function getJoinWith(): array
    {
        return $this->joinWith;
    }

    public function getSql(): string|null
    {
        return $this->sql;
    }

    public function getARClass(): string|null
    {
        return $this->arClass;
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function findOne(mixed $condition): array|ActiveRecordInterface|null
    {
        return $this->findByCondition($condition)->onePopulate();
    }

    /**
     * @param mixed $condition The primary key value or a set of column values.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws Throwable
     *
     * @return array Of ActiveRecord instance, or an empty array if nothing matches.
     */
    public function findAll(mixed $condition): array
    {
        return $this->findByCondition($condition)->all();
    }

    /**
     * Finds ActiveRecord instance(s) by the given condition.
     *
     * This method is internally called by {@see findOne()} and {@see findAll()}.
     *
     * @throws CircularReferenceException
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws NotInstantiableException
     */
    protected function findByCondition(mixed $condition): static
    {
        $arInstance = $this->getARInstance();

        if (!is_array($condition)) {
            $condition = [$condition];
        }

        if (!DbArrayHelper::isAssociative($condition)) {
            /** query by primary key */
            $primaryKey = $arInstance->primaryKey();

            if (isset($primaryKey[0])) {
                $pk = $primaryKey[0];

                if (!empty($this->getJoins()) || !empty($this->getJoinWith())) {
                    $pk = $arInstance->getTableName() . '.' . $pk;
                }

                /**
                 * if the condition is scalar, search for a single primary key, if it's array, search for many primary
                 * key values.
                 */
                $condition = [$pk => array_values($condition)];
            } else {
                throw new InvalidConfigException('"' . $arInstance::class . '" must have a primary key.');
            }
        } else {
            $aliases = $arInstance->filterValidAliases($this);
            $condition = $arInstance->filterCondition($condition, $aliases);
        }

        return $this->where($condition);
    }

    public function findBySql(string $sql, array $params = []): self
    {
        return $this->sql($sql)->params($params);
    }

    public function on(array|string|null $value): self
    {
        $this->on = $value;
        return $this;
    }

    public function sql(string|null $value): self
    {
        $this->sql = $value;
        return $this;
    }

    public function getARInstance(): ActiveRecordInterface
    {
        if ($this->arFactory !== null) {
            return $this->arFactory->createAR($this->arClass, $this->tableName, $this->db);
        }

        /** @psalm-var class-string<ActiveRecordInterface> $class */
        $class = $this->arClass;

        return new $class($this->db, null, $this->tableName);
    }

    private function createInstance(): static
    {
        return (new static($this->arClass, $this->db))
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
            ->setJoins($this->join)
            ->having($this->having)
            ->setUnions($this->union)
            ->params($this->params)
            ->withQueries($this->withQueries);
    }
}
