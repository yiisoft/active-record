<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Redis;

use function addcslashes;
use function array_shift;
use function count;
use function implode;

use function is_array;
use function is_bool;
use function is_int;
use function is_numeric;
use function is_string;
use function key;
use function preg_replace;
use function reset;
use function strtolower;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidParamException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Expression\Expression;

/**
 * LuaScriptBuilder builds lua scripts used for retrieving data from redis.
 */
final class LuaScriptBuilder
{
    /**
     * Builds a Lua script for finding a list of records.
     *
     * @param ActiveQuery $query the query used to build the script.
     *
     * @throws Exception|NotSupportedException
     *
     * @return string
     */
    public function buildAll(ActiveQuery $query): string
    {
        $key = $this->quoteValue($query->getARInstance()->keyPrefix() . ':a:');

        return $this->build($query, "n=n+1 pks[n]=redis.call('HGETALL',$key .. pk)", 'pks');
    }

    /**
     * Builds a Lua script for finding one record.
     *
     * @param ActiveQuery $query the query used to build the script.
     *
     * @throws Exception|NotSupportedException
     *
     * @return string
     */
    public function buildOne(ActiveQuery $query): string
    {
        $key = $this->quoteValue($query->getARInstance()->keyPrefix() . ':a:');

        return $this->build($query, "do return redis.call('HGETALL',$key .. pk) end", 'pks');
    }

    /**
     * Builds a Lua script for finding a column.
     *
     * @param ActiveQuery $query the query used to build the script.
     * @param string $column name of the column.
     *
     * @throws Exception|NotSupportedException
     *
     * @return string
     */
    public function buildColumn(ActiveQuery $query, string $column): string
    {
        $key = $this->quoteValue($query->getARInstance()->keyPrefix() . ':a:');

        return $this->build(
            $query,
            "n=n+1 pks[n]=redis.call('HGET',$key .. pk," . $this->quoteValue($column) . ')',
            'pks'
        );
    }

    /**
     * Builds a Lua script for getting count of records.
     *
     * @param ActiveQuery $query the query used to build the script.
     *
     * @throws Exception|NotSupportedException
     *
     * @return string
     */
    public function buildCount(ActiveQuery $query): string
    {
        return $this->build($query, 'n=n+1', 'n');
    }

    /**
     * Builds a Lua script for finding the sum of a column.
     *
     * @param ActiveQuery $query the query used to build the script.
     * @param string $column name of the column.
     *
     * @throws Exception|NotSupportedException
     *
     * @return string
     */
    public function buildSum(ActiveQuery $query, string $column): string
    {
        $key = $this->quoteValue($query->getARInstance()->keyPrefix() . ':a:');

        return $this->build($query, "n=n+redis.call('HGET',$key .. pk," . $this->quoteValue($column) . ')', 'n');
    }

    /**
     * Builds a Lua script for finding the average of a column.
     *
     * @param ActiveQuery $query the query used to build the script.
     * @param string $column name of the column.
     *
     * @throws Exception|NotSupportedException
     *
     * @return string
     */
    public function buildAverage(ActiveQuery $query, string $column): string
    {
        $key = $this->quoteValue($query->getARInstance()->keyPrefix() . ':a:');

        return $this->build(
            $query,
            "n=n+1 if v==nil then v=0 end v=v+redis.call('HGET',$key .. pk," . $this->quoteValue($column) . ')',
            'v/n'
        );
    }

    /**
     * Builds a Lua script for finding the min value of a column.
     *
     * @param ActiveQuery $query the query used to build the script.
     * @param string $column name of the column.
     *
     * @throws Exception|NotSupportedException
     *
     * @return string
     */
    public function buildMin(ActiveQuery $query, string $column): string
    {
        $key = $this->quoteValue($query->getARInstance()->keyPrefix() . ':a:');

        return $this->build(
            $query,
            "n=redis.call('HGET',$key .. pk," . $this->quoteValue($column) . ') if v==nil or n<v then v=n end',
            'v'
        );
    }

    /**
     * Builds a Lua script for finding the max value of a column.
     *
     * @param ActiveQuery $query the query used to build the script.
     * @param string $column name of the column.
     *
     * @throws Exception|NotSupportedException
     *
     * @return string
     */
    public function buildMax(ActiveQuery $query, string $column): string
    {
        $key = $this->quoteValue($query->getARInstance()->keyPrefix() . ':a:');

        return $this->build(
            $query,
            "n=redis.call('HGET',$key .. pk," . $this->quoteValue($column) . ') if v==nil or n>v then v=n end',
            'v'
        );
    }

    /**
     * @param ActiveQuery $query the query used to build the script.
     * @param string $buildResult the lua script for building the result.
     * @param string $return the lua variable that should be returned.
     *
     * @throws Exception|NotSupportedException when query contains unsupported order by condition.
     *
     * @return string
     */
    private function build(ActiveQuery $query, string $buildResult, string $return): string
    {
        $columns = [];

        if ($query->getWhere() !== null) {
            $condition = $this->buildCondition($query->getWhere(), $columns);
        } else {
            $condition = 'true';
        }

        $start = ($query->getOffset() === null || $query->getOffset() < 0) ? 0 : $query->getOffset();
        $limitCondition = 'i>' . $start . (
            ($query->getLimit() === null || $query->getLimit() < 0) ? '' : ' and i<=' . ($start + $query->getLimit())
        );

        $key = $this->quoteValue($query->getARInstance()->keyPrefix());

        $loadColumnValues = '';

        foreach ($columns as $column => $alias) {
            $loadColumnValues .= "local $alias=redis.call('HGET',$key .. ':a:' .. pk, "
                . $this->quoteValue($column) . ")\n";
        }

        $getAllPks = <<<EOF
local allpks=redis.call('LRANGE',$key,0,-1)
EOF;
        if (!empty($query->getOrderBy())) {
            if (!is_array($query->getOrderBy()) || count($query->getOrderBy()) > 1) {
                throw new NotSupportedException(
                    'orderBy by multiple columns is not currently supported by redis ActiveRecord.'
                );
            }

            $k = key($query->getOrderBy());
            $v = $query->getOrderBy()[$k];

            if (is_numeric($k)) {
                $orderColumn = $v;
                $orderType = 'ASC';
            } else {
                $orderColumn = $k;
                $orderType = $v === SORT_DESC ? 'DESC' : 'ASC';
            }

            $getAllPks = <<<EOF
local allpks=redis.pcall('SORT', $key, 'BY', $key .. ':a:*->' .. '$orderColumn', '$orderType')
if allpks['err'] then
    allpks=redis.pcall('SORT', $key, 'BY', $key .. ':a:*->' .. '$orderColumn', '$orderType', 'ALPHA')
end
EOF;
        }

        return <<<EOF
$getAllPks
local pks={}
local n=0
local v=nil
local i=0
local key=$key
for k,pk in ipairs(allpks) do
    $loadColumnValues
    if $condition then
      i=i+1
      if $limitCondition then
        $buildResult
      end
    end
end
return $return
EOF;
    }

    /**
     * Adds a column to the list of columns to retrieve and creates an alias.
     *
     * @param string $column the column name to add.
     * @param array $columns list of columns given by reference.
     *
     * @return string the alias generated for the column name.
     */
    private function addColumn(string $column, array &$columns = []): string
    {
        if (isset($columns[$column])) {
            return $columns[$column];
        }

        $name = 'c' . preg_replace('/[^a-z]+/i', '', $column) . count($columns);

        return $columns[$column] = $name;
    }

    /**
     * Quotes a string value for use in a query.
     *
     * Note that if the parameter is not a string or int, it will be returned without change.
     *
     * @param int|string $str string to be quoted.
     *
     * @return int|string the properly quoted string.
     */
    private function quoteValue($str)
    {
        if (!is_string($str) && !is_int($str)) {
            return $str;
        }

        return "'" . addcslashes((string) $str, "\000\n\r\\\032\047") . "'";
    }

    /**
     * Parses the condition specification and generates the corresponding Lua expression.
     *
     * @param array|string $condition the condition specification. Please refer to {@see ActiveQuery::where()} on how
     * to specify a condition.
     * @param array $columns the list of columns and aliases to be used.
     *
     * @throws Exception if the condition is in bad format.
     * @throws NotSupportedException if the condition is not an array.
     *
     * @return string the generated SQL expression.
     */
    public function buildCondition($condition, array &$columns = []): string
    {
        static $builders = [
            'not' => 'buildNotCondition',
            'and' => 'buildAndCondition',
            'or' => 'buildAndCondition',
            'between' => 'buildBetweenCondition',
            'not between' => 'buildBetweenCondition',
            'in' => 'buildInCondition',
            'not in' => 'buildInCondition',
            'like' => 'buildLikeCondition',
            'not like' => 'buildLikeCondition',
            'or like' => 'buildLikeCondition',
            'or not like' => 'buildLikeCondition',
        ];

        if (!is_array($condition)) {
            throw new NotSupportedException('Where condition must be an array in redis ActiveRecord.');
        }

        /** operator format: operator, operand 1, operand 2, ... */
        if (isset($condition[0])) {
            $operator = strtolower($condition[0]);
            if (isset($builders[$operator])) {
                $method = $builders[$operator];
                array_shift($condition);

                return $this->$method($operator, $condition, $columns);
            }

            throw new Exception('Found unknown operator in query: ' . $operator);
        }

        /** hash format: 'column1' => 'value1', 'column2' => 'value2', ... */
        return $this->buildHashCondition($condition, $columns);
    }

    private function buildHashCondition(array $condition, array &$columns): string
    {
        $parts = [];

        foreach ($condition as $column => $value) {
            /** IN condition */
            if (is_array($value)) {
                $parts[] = $this->buildInCondition('in', [$column, $value], $columns);
            } else {
                if (is_bool($value)) {
                    $value = (int) $value;
                }

                if ($value === null) {
                    $parts[] = "redis.call('HEXISTS',key .. ':a:' .. pk, " . $this->quoteValue($column) . ')==0';
                } elseif ($value instanceof Expression) {
                    $column = $this->addColumn($column, $columns);

                    $parts[] = "$column==" . $value;
                } else {
                    $column = $this->addColumn($column, $columns);
                    $value = $this->quoteValue((string) $value);

                    $parts[] = "$column==$value";
                }
            }
        }

        return count($parts) === 1 ? $parts[0] : '(' . implode(') and (', $parts) . ')';
    }

    private function buildNotCondition($operator, $operands, &$params): string
    {
        if (count($operands) !== 1) {
            throw new InvalidParamException("Operator '$operator' requires exactly one operand.");
        }

        $operand = reset($operands);

        if (is_array($operand)) {
            $operand = $this->buildCondition($operand, $params);
        }

        return "$operator ($operand)";
    }

    private function buildAndCondition($operator, $operands, &$columns): string
    {
        $parts = [];

        foreach ($operands as $operand) {
            if (is_array($operand)) {
                $operand = $this->buildCondition($operand, $columns);
            }
            if ($operand !== '') {
                $parts[] = $operand;
            }
        }

        if (!empty($parts)) {
            return '(' . implode(") $operator (", $parts) . ')';
        }

        return '';
    }

    private function buildBetweenCondition($operator, $operands, &$columns): string
    {
        if (!isset($operands[0], $operands[1], $operands[2])) {
            throw new Exception("Operator '$operator' requires three operands.");
        }

        [$column, $value1, $value2] = $operands;

        $value1 = $this->quoteValue($value1);
        $value2 = $this->quoteValue($value2);
        $column = $this->addColumn($column, $columns);

        $condition = "$column >= $value1 and $column <= $value2";

        return $operator === 'not between' ? "not ($condition)" : $condition;
    }

    /**
     * @param string $operator
     * @param array $operands
     * @param (array|mixed)[] $operands
     *
     * @throws Exception
     *
     * @return string
     */
    private function buildInCondition(string $operator, array $operands, &$columns): string
    {
        if (!isset($operands[0], $operands[1])) {
            throw new Exception("Operator '$operator' requires two operands.");
        }

        [$column, $values] = $operands;

        $values = (array) $values;

        if (empty($values) || $column === []) {
            return $operator === 'in' ? 'false' : 'true';
        }

        if (is_array($column) && count($column) > 1) {
            return $this->buildCompositeInCondition($operator, $column, $values, $columns);
        }

        if (is_array($column)) {
            $column = reset($column);
        }

        $columnAlias = $this->addColumn((string) $column, $columns);
        $parts = [];

        foreach ($values as $value) {
            if (is_array($value)) {
                $value = $value[$column] ?? null;
            }

            if ($value === null) {
                $parts[] = "redis.call('HEXISTS',key .. ':a:' .. pk, " . $this->quoteValue($column) . ')==0';
            } elseif ($value instanceof Expression) {
                $parts[] = "$columnAlias==" . $value;
            } else {
                $value = $this->quoteValue($value);
                $parts[] = "$columnAlias==$value";
            }
        }

        $operator = $operator === 'in' ? '' : 'not ';

        return "$operator(" . implode(' or ', $parts) . ')';
    }

    protected function buildCompositeInCondition($operator, array $inColumns, array $values, &$columns): string
    {
        $vss = [];

        foreach ($values as $value) {
            $vs = [];
            foreach ($inColumns as $column) {
                if (isset($value[$column])) {
                    $columnAlias = $this->addColumn($column, $columns);
                    $vs[] = "$columnAlias==" . $this->quoteValue($value[$column]);
                } else {
                    $vs[] = "redis.call('HEXISTS',key .. ':a:' .. pk, " . $this->quoteValue($column) . ')==0';
                }
            }

            $vss[] = '(' . implode(' and ', $vs) . ')';
        }

        $operator = $operator === 'in' ? '' : 'not ';

        return "$operator(" . implode(' or ', $vss) . ')';
    }

    private function buildLikeCondition($operator, $operands, &$columns): void
    {
        throw new NotSupportedException('LIKE conditions are not suppoerted by redis ActiveRecord.');
    }
}
