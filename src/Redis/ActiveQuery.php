<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Redis;

use JsonException;
use ReflectionException;
use Yiisoft\ActiveRecord\ActiveQuery as BaseActiveQuery;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\InvalidParamException;
use Yiisoft\Db\Exception\NotSupportedException;

use function array_keys;
use function arsort;
use function asort;
use function count;
use function get_class;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function key;
use function reset;

/**
 * ActiveQuery represents a query associated with an Active Record class.
 *
 * An ActiveQuery can be a normal query or be used in a relational context.
 *
 * ActiveQuery instances are usually created by {@see ActiveRecord::find()}.
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
 * - {@see exists()}: returns a value indicating whether the query result has data or not.
 *
 * You can use query methods, such as {@see where()}, {@see limit()} and {@see orderBy()} to customize the query
 * options.
 *
 * ActiveQuery also provides the following additional query options:
 *
 * - {@see with()}: list of relations that this query should be performed with.
 * - {@see indexBy()}: the name of the column by which the query result should be indexed.
 * - {@see asArray()}: whether to return each record as an array.
 *
 * These options can be configured using methods of the same name. For example:
 *
 * ```php
 * $customers = Customer::find()->with('orders')->asArray()->all();
 * ```
 *
 * Relational query
 * ----------------
 *
 * In relational context ActiveQuery represents a relation between two Active Record classes.
 *
 * Relational ActiveQuery instances are usually created by calling {@see ActiveRecord::hasOne()} and
 * {@see ActiveRecord::hasMany()}. An Active Record class declares a relation by defining
 * a getter method which calls one of the above methods and returns the created ActiveQuery object.
 *
 * A relation is specified by {@see link} which represents the association between columns of different tables; and the
 * multiplicity of the relation is indicated by {@see multiple}.
 *
 * If a relation involves a junction table, it may be specified by {@see via()}.
 *
 * This methods may only be called in a relational context. Same is true for {@see inverseOf()}, which
 * marks a relation as inverse of another relation.
 */
class ActiveQuery extends BaseActiveQuery
{
    private string $attribute;
    private ?LuaScriptBuilder $luaScriptBuilder = null;

    /**
     * Executes the query and returns all results as an array.
     *
     * @throws Exception|JsonException|InvalidConfigException|InvalidParamException|ReflectionException
     * @throws NotSupportedException
     *
     * @return array the query results. If the query results in nothing, an empty array will be returned.
     */
    public function all(): array
    {
        $indexBy = $this->getIndexBy();

        if ($this->shouldEmulateExecution()) {
            return [];
        }

        /** support for orderBy */
        $data = $this->executeScript('All');

        if (empty($data)) {
            return [];
        }

        $rows = [];

        foreach ($data as $dataRow) {
            $row = [];
            $c = count($dataRow);
            for ($i = 0; $i < $c;) {
                $row[$dataRow[$i++]] = $dataRow[$i++];
            }

            $rows[] = $row;
        }

        if (empty($rows)) {
            return [];
        }

        $models = $this->createModels($rows);

        if (!empty($this->getWith())) {
            $this->findWith($this->getWith(), $models);
        }

        if ($indexBy !== null) {
            $indexedModels = [];
            if (is_string($indexBy)) {
                foreach ($models as $model) {
                    $key = $model[$indexBy];
                    $indexedModels[$key] = $model;
                }
            } else {
                foreach ($models as $model) {
                    $key = $this->indexBy($model);
                    $indexedModels[$key] = $model;
                }
            }
            $models = $indexedModels;
        }

        return $models;
    }

    /**
     * Executes the query and returns a single row of result.
     *
     * Null will be returned, if the query results in nothing.
     *
     * @throws Exception|JsonException|InvalidConfigException|InvalidParamException|ReflectionException
     * @throws NotSupportedException
     *
     * @return ActiveRecord|array|null a single row of query result. Depending on the setting of {@see asArray}, the
     * query result may be either an array or an ActiveRecord object.
     */
    public function one()
    {
        if ($this->shouldEmulateExecution()) {
            return null;
        }

        /** add support for orderBy */
        $data = $this->executeScript('One');

        if (empty($data)) {
            return null;
        }

        $row = [];

        $c = count($data);

        for ($i = 0; $i < $c;) {
            $row[$data[$i++]] = $data[$i++];
        }

        if ($this->isAsArray()) {
            $model = $row;
        } else {
            /** @var $class ActiveRecord */
            $class = $this->arClass;

            $model = new $class($this->db);

            $model->populateRecord($model, $row);
        }

        if (!empty($this->getWith())) {
            $models = [$model];

            $this->findWith($this->getWith(), $models);

            $model = $models[0];
        }

        return $model;
    }

    /**
     * Returns the number of records.
     *
     * @param string $q the COUNT expression. This parameter is ignored by this implementation.
     *
     * @throws Exception|JsonException|InvalidConfigException|InvalidParamException|ReflectionException
     * @throws NotSupportedException
     *
     * @return int number of records.
     */
    public function count(string $q = '*'): int
    {
        if ($this->shouldEmulateExecution()) {
            return 0;
        }

        if ($this->getWhere() === null) {
            /* @var $modelClass ActiveRecord */
            $modelClass = $this->arClass;

            return (int) $this->db->executeCommand('LLEN', [$modelClass::keyPrefix()]);
        }

        return (int) $this->executeScript('Count');
    }

    /**
     * Returns a value indicating whether the query result contains any row of data.
     *
     * @throws Exception|JsonException|InvalidConfigException|InvalidParamException|ReflectionException
     * @throws NotSupportedException
     *
     * @return bool whether the query result contains any row of data.
     */
    public function exists(): bool
    {
        if ($this->shouldEmulateExecution()) {
            return false;
        }

        return $this->one() !== null;
    }

    /**
     * Returns the number of records.
     *
     * @param string $column the column to sum up. If this parameter is not given, the `db` application component will
     * be used.
     *
     * @throws Exception|JsonException|InvalidConfigException|InvalidParamException|ReflectionException
     * @throws NotSupportedException
     *
     * @return int number of records.
     */
    public function sum(string $column): int
    {
        if ($this->shouldEmulateExecution()) {
            return 0;
        }

        return (int) $this->executeScript('Sum', !empty($column) ? $column : $this->attribute);
    }

    /**
     * Returns the average of the specified column values.
     *
     * @param string $column the column name or expression. Make sure you properly quote column names in the expression.
     *
     * @throws Exception|JsonException|InvalidConfigException|InvalidParamException|ReflectionException
     * @throws NotSupportedException
     *
     * @return int the average of the specified column values.
     */
    public function average(string $column): int
    {
        if ($this->shouldEmulateExecution()) {
            return 0;
        }

        return (int) $this->executeScript('Average', !empty($column) ? $column : $this->attribute);
    }

    /**
     * Returns the minimum of the specified column values.
     *
     * @param string $column the column name or expression. Make sure you properly quote column names in the expression.
     *
     * @throws Exception|JsonException|InvalidConfigException|InvalidParamException|ReflectionException
     * @throws NotSupportedException
     *
     * @return int the minimum of the specified column values.
     */
    public function min(string $column): ?int
    {
        if ($this->shouldEmulateExecution()) {
            return null;
        }

        return (int) $this->executeScript('Min', !empty($column) ? $column : $this->attribute);
    }

    /**
     * Returns the maximum of the specified column values.
     *
     * @param string $column the column name or expression. Make sure you properly quote column names in the expression.
     *
     * @throws Exception|JsonException|InvalidConfigException|InvalidParamException|ReflectionException
     * @throws NotSupportedException
     *
     * @return int the maximum of the specified column values.
     */
    public function max(string $column): ?int
    {
        if ($this->shouldEmulateExecution()) {
            return null;
        }

        return (int) $this->executeScript('Max', !empty($column) ? $column : $this->attribute);
    }

    /**
     * Executes a script created by {@see LuaScriptBuilder}.
     *
     * @param string $type the type of the script to generate
     * @param string|null $columnName
     *
     * @throws Exception|JsonException|InvalidConfigException|InvalidParamException|ReflectionException
     * @throws NotSupportedException
     *
     * @return array|bool|null|string
     */
    protected function executeScript(string $type, ?string $columnName = null)
    {
        if ($this->getPrimaryModel() !== null) {
            /** lazy loading */
            if ($this->getVia() instanceof self) {
                /** via junction table */
                $viaModels = $this->getVia()->findJunctionRows([$this->getPrimaryModel()]);
                $this->filterByModels($viaModels);
            } elseif (is_array($this->getVia())) {
                /**
                 * via relation
                 * @var $viaQuery ActiveQuery
                 */
                [$viaName, $viaQuery] = $this->getVia();

                if ($viaQuery->getMultiple()) {
                    $viaModels = $viaQuery->all();
                    $this->getPrimaryModel()->populateRelation($viaName, $viaModels);
                } else {
                    $model = $viaQuery->one();
                    $this->getPrimaryModel()->populateRelation($viaName, $model);
                    $viaModels = $model === null ? [] : [$model];
                }

                $this->filterByModels($viaModels);
            } else {
                $this->filterByModels([$this->getPrimaryModel()]);
            }
        }

        /** find by primary key if possible. This is much faster than scanning all records */
        if (
            is_array($this->getWhere()) &&
            (
                (!isset($this->getWhere()[0]) && $this->getARInstance()->isPrimaryKey(array_keys($this->getWhere()))) ||
                (
                    isset($this->getWhere()[0]) && $this->getWhere()[0] === 'in' &&
                    $this->getARInstance()->isPrimaryKey((array) $this->getWhere()[1])
                )
            )
        ) {
            return $this->findByPk($type, $columnName);
        }

        $method = 'build' . $type;

        $script = $this->getLuaScriptBuilder()->$method($this, $columnName);

        return $this->db->executeCommand('EVAL', [$script, 0]);
    }

    /**
     * Fetch by pk if possible as this is much faster.
     *
     * @param string $type the type of the script to generate.
     * @param string|null $columnName
     *
     * @throws InvalidParamException|JsonException|NotSupportedException
     *
     * @return array|bool|null|string
     */
    private function findByPk(string $type, ?string $columnName = null)
    {
        $limit = $this->getLimit();
        $offset = $this->getOffset();
        $orderBy = $this->getOrderBy();
        $needSort = !empty($orderBy) && in_array($type, ['All', 'One', 'Column']);
        $where = $this->getWhere();

        if ($needSort) {
            if (!is_array($orderBy) || count($orderBy) > 1) {
                throw new NotSupportedException(
                    'orderBy by multiple columns is not currently supported by redis ActiveRecord.'
                );
            }

            $k = key($orderBy);
            $v = $orderBy[$k];

            if (is_numeric($k)) {
                $orderColumn = $v;
                $orderType = SORT_ASC;
            } else {
                $orderColumn = $k;
                $orderType = $v;
            }
        }

        if (isset($where[0]) && $where[0] === 'in') {
            $pks = (array) $where[2];
        } elseif (count($where) == 1) {
            $pks = (array) reset($where);
        } else {
            foreach ($where as $values) {
                if (is_array($values)) {
                    /** support composite IN for composite PK */
                    throw new NotSupportedException('Find by composite PK is not supported by redis ActiveRecord.');
                }
            }
            $pks = [$where];
        }

        /** @var $modelClass ActiveRecord */
        $modelClass = $this->arClass;

        if ($type === 'Count') {
            $start = 0;
            $limit = null;
        } else {
            $start = ($offset === null || $offset < 0) ? 0 : $offset;
            $limit = ($limit < 0) ? null : $limit;
        }

        $i = 0;
        $data = [];
        $orderArray = [];

        foreach ($pks as $pk) {
            if (++$i > $start && ($limit === null || $i <= $start + $limit)) {
                $key = $modelClass::keyPrefix() . ':a:' . $modelClass::buildKey($pk);
                $result = $this->db->executeCommand('HGETALL', [$key]);
                if (!empty($result)) {
                    $data[] = $result;
                    if ($needSort) {
                        $orderArray[] = $this->db->executeCommand(
                            'HGET',
                            [$key, $orderColumn]
                        );
                    }
                    if ($type === 'One' && $orderBy === null) {
                        break;
                    }
                }
            }
        }

        if ($needSort) {
            $resultData = [];

            if ($orderType === SORT_ASC) {
                asort($orderArray, SORT_NATURAL);
            } else {
                arsort($orderArray, SORT_NATURAL);
            }

            foreach ($orderArray as $orderKey => $orderItem) {
                $resultData[] = $data[$orderKey];
            }

            $data = $resultData;
        }

        switch ($type) {
            case 'All':
                return $data;
            case 'One':
                return reset($data);
            case 'Count':
                return count($data);
            case 'Column':
                $column = [];
                foreach ($data as $dataRow) {
                    $row = [];
                    $c = count($dataRow);
                    for ($i = 0; $i < $c;) {
                        $row[$dataRow[$i++]] = $dataRow[$i++];
                    }
                    $column[] = $row[$columnName];
                }

                return $column;
            case 'Sum':
                $sum = 0;
                foreach ($data as $dataRow) {
                    $c = count($dataRow);
                    for ($i = 0; $i < $c;) {
                        if ($dataRow[$i++] == $columnName) {
                            $sum += $dataRow[$i];
                            break;
                        }
                    }
                }

                return $sum;
            case 'Average':
                $sum = 0;
                $count = 0;
                foreach ($data as $dataRow) {
                    $count++;
                    $c = count($dataRow);
                    for ($i = 0; $i < $c;) {
                        if ($dataRow[$i++] == $columnName) {
                            $sum += $dataRow[$i];
                            break;
                        }
                    }
                }

                return $sum / $count;
            case 'Min':
                $min = null;
                foreach ($data as $dataRow) {
                    $c = count($dataRow);
                    for ($i = 0; $i < $c;) {
                        if ($dataRow[$i++] == $columnName && ($min == null || $dataRow[$i] < $min)) {
                            $min = $dataRow[$i];
                            break;
                        }
                    }
                }

                return $min;
            case 'Max':
                $max = null;
                foreach ($data as $dataRow) {
                    $c = count($dataRow);
                    for ($i = 0; $i < $c;) {
                        if ($dataRow[$i++] == $columnName && ($max == null || $dataRow[$i] > $max)) {
                            $max = $dataRow[$i];
                            break;
                        }
                    }
                }

                return $max;
        }

        throw new InvalidParamException('Unknown fetch type: ' . $type);
    }

    /**
     * Executes the query and returns the first column of the result.
     *
     * @throws Exception|InvalidConfigException|InvalidParamException|ReflectionException|NotSupportedException
     *
     * @return array the first column of the query result. An empty array is returned if the query results in nothing.
     */
    public function column(): array
    {
        if ($this->shouldEmulateExecution()) {
            return [];
        }

        /** TODO add support for orderBy */
        return $this->executeScript('Column', $this->attribute);
    }

    /**
     * Returns the query result as a scalar value.
     *
     * The value returned will be the specified attribute in the first record of the query results.
     *
     * @throws Exception|InvalidConfigException|InvalidParamException|ReflectionException|NotSupportedException
     *
     * @return string|null the value of the specified attribute in the first record of the query result. Null is
     * returned if the query result is empty.
     */
    public function scalar(): ?string
    {
        if ($this->shouldEmulateExecution()) {
            return null;
        }

        $record = $this->one();

        if ($record !== null) {
            return $record->hasAttribute($this->attribute) ? $record->getAttribute($this->attribute) : null;
        }

        return null;
    }

    public function withAttribute(string $value): self
    {
        $this->attribute = $value;

        return $this;
    }

    public function getLuaScriptBuilder(): LuaScriptBuilder
    {
        if ($this->luaScriptBuilder === null) {
            $this->luaScriptBuilder = new LuaScriptBuilder();
        }

        return $this->luaScriptBuilder;
    }
}
