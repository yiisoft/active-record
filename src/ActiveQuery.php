<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Closure;
use ReflectionException;
use Throwable;
use Yiisoft\Db\Command\CommandInterface;
use Yiisoft\Db\Exception\Exception;
use InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Expression\ExpressionInterface;
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
 * $customerQuery = new ActiveQuery(Customer::class);
 * $query = $customerQuery->with('orders')->asArray()->all();
 * ```
 *
 * Relational query
 * ----------------
 *
 * In relational context, ActiveQuery represents a relation between two Active Record classes.
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
 * These methods may only be called in a relational context. The same is true for {@see inverseOf()}, which marks a relation
 * as inverse of another relation and {@see onCondition()} which adds a condition that is to be added to relational
 * query join condition.
 *
 * @psalm-import-type ARClass from ActiveQueryInterface
 * @psalm-import-type IndexKey from ArArrayHelper
 *
 * @psalm-property IndexKey|null $indexBy
 * @psalm-suppress ClassMustBeFinal
 */
class ActiveQuery extends Query implements ActiveQueryInterface
{
    use ActiveQueryTrait;
    use ActiveRelationTrait;

    private string|null $sql = null;
    private array|string|null $on = null;
    private array $joinWith = [];

    /**
     * @psalm-param ARClass $arClass
     */
    final public function __construct(
        protected string|ActiveRecordInterface|Closure $arClass
    ) {
        parent::__construct($this->getArInstance()->db());
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

            if ($this->via instanceof ActiveQueryInterface) {
                /** via junction table */
                /** @var self $this->via */
                $viaModels = $this->via->findJunctionRows([$this->primaryModel]);

                $this->filterByModels($viaModels);
            } elseif (is_array($this->via)) {
                [$viaName, $viaQuery, $viaCallableUsed] = $this->via;

                if ($viaQuery->getMultiple()) {
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
                $this->filterByModels($viaModels);
            } else {
                $this->filterByModels([$this->primaryModel]);
            }

            $query = $this->createInstance();
            $this->setWhere($where);
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
     *
     * @psalm-param list<array> $rows
     * @psalm-return (
     *     $rows is non-empty-list<array>
     *         ? non-empty-list<ActiveRecordInterface|array>
     *         : list<ActiveRecordInterface|array>
     * )
     */
    public function populate(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        if (!empty($this->join) && $this->indexBy === null) {
            $rows = $this->removeDuplicatedRows($rows);
        }

        $models = $this->createModels($rows);

        if (!empty($this->with)) {
            $this->findWith($this->with, $models);
        }

        if ($this->inverseOf !== null) {
            $this->addInverseRelations($models);
        }

        return $models;
    }

    /**
     * Removes duplicated rows by checking their primary key values.
     *
     * This method is mainly called when a join query is performed, which may cause duplicated rows being returned.
     *
     * @param array[] $rows The rows to be checked.
     *
     * @throws CircularReferenceException
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotInstantiableException
     *
     * @return array[] The distinctive rows.
     *
     * @psalm-param non-empty-list<array> $rows
     * @psalm-return non-empty-list<array>
     */
    private function removeDuplicatedRows(array $rows): array
    {
        $instance = $this->getArInstance();
        $pks = $instance->primaryKey();

        if (empty($pks)) {
            throw new InvalidConfigException('Primary key of "' . $instance::class . '" can not be empty.');
        }

        foreach ($pks as $pk) {
            if (!isset($rows[0][$pk])) {
                return $rows;
            }
        }

        if (count($pks) === 1) {
            $hash = array_column($rows, reset($pks));
        } else {
            $flippedPks = array_flip($pks);
            $hash = array_map(
                static fn (array $row): string => serialize(array_intersect_key($row, $flippedPks)),
                $rows
            );
        }

        /** @psalm-var non-empty-list<array> */
        return array_values(array_combine($hash, $rows));
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
    ): static {
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
     * @throws NotInstantiableException
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
     */
    public function buildJoinWith(): void
    {
        $join = $this->join;

        $this->join = [];

        $arClass = $this->getArInstance();

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

    public function innerJoinWith(array|string $with, array|bool $eagerLoading = true): static
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
                    $primaryModel = $relation->getArInstance();
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
     * @throws NotInstantiableException
     * @throws InvalidConfigException
     */
    private function joinWithRelation(ActiveQueryInterface $parent, ActiveQueryInterface $child, string $joinType): void
    {
        $via = $child->getVia();
        /** @var ActiveQuery $child */
        $child->via = null;

        if ($via instanceof ActiveQueryInterface) {
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

    public function onCondition(array|string $condition, array $params = []): static
    {
        $this->on = $condition;

        $this->addParams($params);

        return $this;
    }

    public function andOnCondition(array|string $condition, array $params = []): static
    {
        if ($this->on === null) {
            $this->on = $condition;
        } else {
            $this->on = ['and', $this->on, $condition];
        }

        $this->addParams($params);

        return $this;
    }

    public function orOnCondition(array|string $condition, array $params = []): static
    {
        if ($this->on === null) {
            $this->on = $condition;
        } else {
            $this->on = ['or', $this->on, $condition];
        }

        $this->addParams($params);

        return $this;
    }

    public function viaTable(string $tableName, array $link, callable|null $callable = null): static
    {
        $arClass = $this->primaryModel ?? $this->arClass;

        $relation = (new static($arClass))
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

    public function getTablesUsedInFrom(): array
    {
        if (empty($this->from)) {
            return $this->db->getQuoter()->cleanUpTableNames([$this->getPrimaryTableName()]);
        }

        return parent::getTablesUsedInFrom();
    }

    protected function getPrimaryTableName(): string
    {
        return $this->getArInstance()->tableName();
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

    public function getArClass(): string|ActiveRecordInterface|Closure
    {
        return $this->arClass;
    }

    public function findByPk(array|float|int|string $values): array|ActiveRecordInterface|null
    {
        $values = (array) $values;

        $arInstance = $this->getArInstance();
        $primaryKey = $arInstance->primaryKey();

        if (empty($primaryKey)) {
            throw new InvalidConfigException($arInstance::class . ' must have a primary key.');
        }

        if (count($primaryKey) !== count($values)) {
            throw new InvalidArgumentException(
                'The primary key has ' . count($primaryKey) . ' columns, but ' . count($values) . ' values are passed.'
            );
        }

        if (!empty($this->getJoins()) || !empty($this->getJoinWith())) {
            $tableName = $arInstance->tableName();

            foreach ($primaryKey as &$pk) {
                $pk = "$tableName.$pk";
            }
        }

        return $this->setWhere(array_combine($primaryKey, $values))->one();
    }

    public function on(array|string|null $value): static
    {
        $this->on = $value;
        return $this;
    }

    public function sql(string|null $value): static
    {
        $this->sql = $value;
        return $this;
    }

    public function getArInstance(): ActiveRecordInterface
    {
        if ($this->arClass instanceof ActiveRecordInterface) {
            return clone $this->arClass;
        }

        if ($this->arClass instanceof Closure) {
            return ($this->arClass)();
        }

        /** @psalm-var class-string<ActiveRecordInterface> $class */
        $class = $this->arClass;

        return new $class();
    }

    protected function index(array $rows): array
    {
        return ArArrayHelper::index($this->populate($rows), $this->indexBy);
    }

    private function createInstance(): static
    {
        return (new static($this->arClass))
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

    private function populateOne(array $row): ActiveRecordInterface|array
    {
        return $this->populate([$row])[0];
    }
}
