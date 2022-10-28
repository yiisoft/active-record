<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Throwable;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\StaleObjectException;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Schema\TableSchemaInterface;
use Yiisoft\Strings\Inflector;
use Yiisoft\Strings\StringHelper;

use function array_diff;
use function array_keys;
use function array_map;
use function array_values;
use function in_array;
use function is_array;
use function is_string;
use function key;
use function preg_replace;

/**
 * ActiveRecord is the base class for classes representing relational data in terms of objects.
 *
 * Active Record implements the [Active Record design pattern](http://en.wikipedia.org/wiki/Active_record).
 *
 * The premise behind Active Record is that an individual {@see ActiveRecord} object is associated with a specific row
 * in a database table. The object's attributes are mapped to the columns of the corresponding table.
 *
 * Referencing an Active Record attribute is equivalent to accessing the corresponding table column for that record.
 *
 * As an example, say that the `Customer` ActiveRecord class is associated with the `customer` table.
 *
 * This would mean that the class's `name` attribute is automatically mapped to the `name` column in `customer` table.
 * Thanks to Active Record, assuming the variable `$customer` is an object of type `Customer`, to get the value of the
 * `name` column for the table row, you can use the expression `$customer->name`.
 *
 * In this example, Active Record is providing an object-oriented interface for accessing data stored in the database.
 * But Active Record provides much more functionality than this.
 *
 * To declare an ActiveRecord class you need to extend {@see ActiveRecord} and implement the `tableName` method:
 *
 * ```php
 * <?php
 *
 * class Customer extends ActiveRecord
 * {
 *     public static function tableName(): string
 *     {
 *         return 'customer';
 *     }
 * }
 * ```
 *
 * The `tableName` method only has to return the name of the database table associated with the class.
 *
 * Class instances are obtained in one of two ways:
 *
 * Using the `new` operator to create a new, empty object.
 * Using a method to fetch an existing record (or records) from the database.
 *
 * Below is an example showing some typical usage of ActiveRecord:
 *
 * ```php
 * $user = new User($db);
 * $user->name = 'Qiang';
 * $user->save();  // a new row is inserted into user table
 *
 * // the following will retrieve the user 'CeBe' from the database
 * $userQuery = new ActiveQuery(User::class, $db);
 * $user = $userQuery->where(['name' => 'CeBe'])->one();
 *
 * // this will get related records from orders table when relation is defined
 * $orders = $user->orders;
 * ```
 *
 * For more details and usage information on ActiveRecord,
 * {@see the [guide article on ActiveRecord](guide:db-active-record)}
 *
 * @method ActiveQuery hasMany($class, array $link) {@see BaseActiveRecord::hasMany()} for more info.
 * @method ActiveQuery hasOne($class, array $link) {@see BaseActiveRecord::hasOne()} for more info.
 */
class ActiveRecord extends BaseActiveRecord
{
    /**
     * The insert operation. This is mainly used when overriding {@see transactions()} to specify which operations are
     * transactional.
     */
    public const OP_INSERT = 0x01;

    /**
     * The update operation. This is mainly used when overriding {@see transactions()} to specify which operations are
     * transactional.
     */
    public const OP_UPDATE = 0x02;

    /**
     * The delete operation. This is mainly used when overriding {@see transactions()} to specify which operations are
     * transactional.
     */
    public const OP_DELETE = 0x04;

    /**
     * All three operations: insert, update, delete.
     *
     * This is a shortcut of the expression: OP_INSERT | OP_UPDATE | OP_DELETE.
     */
    public const OP_ALL = 0x07;

    public function attributes(): array
    {
        return array_keys($this->getTableSchema()->getColumns());
    }

    public function delete(): false|int
    {
        if (!$this->isTransactional(self::OP_DELETE)) {
            return $this->deleteInternal();
        }

        $transaction = $this->db->beginTransaction();

        try {
            $result = $this->deleteInternal();
            if ($result === false) {
                $transaction->rollBack();
            } else {
                $transaction->commit();
            }

            return $result;
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    public function deleteAll(array $condition = [], array $params = []): int
    {
        $command = $this->db->createCommand();
        $command->delete(static::tableName(), $condition, $params);

        return $command->execute();
    }

    public function equals(ActiveRecordInterface $record): bool
    {
        if ($this->isNewRecord || $record->isNewRecord) {
            return false;
        }

        return static::tableName() === $record::tableName() && $this->getPrimaryKey() === $record->getPrimaryKey();
    }

    /**
     * Filters array condition before it is assigned to a Query filter.
     *
     * This method will ensure that an array condition only filters on existing table columns.
     *
     * @param array $condition Condition to filter.
     * @param array $aliases Aliases to be used for table names.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException In case array contains unsafe values.
     *
     * @return array Filtered condition.
     */
    public function filterCondition(array $condition, array $aliases = []): array
    {
        $result = [];

        $columnNames = $this->filterValidColumnNames($aliases);

        foreach ($condition as $key => $value) {
            if (is_string($key) && !in_array($this->db->getQuoter()->quoteSql($key), $columnNames, true)) {
                throw new InvalidArgumentException(
                    'Key "' . $key . '" is not a column name and can not be used as a filter'
                );
            }
            $result[$key] = is_array($value) ? array_values($value) : $value;
        }

        return $result;
    }

    /**
     * Returns table aliases which are not the same as the name of the tables.
     *
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     */
    public function filterValidAliases(ActiveQuery $query): array
    {
        $tables = $query->getTablesUsedInFrom();

        $aliases = array_diff(array_keys($tables), $tables);

        return array_map(static fn ($alias) => preg_replace('/{{([\w]+)}}/', '$1', $alias), array_values($aliases));
    }

    /**
     * Returns the schema information of the DB table associated with this AR class.
     *
     * @throws Exception
     * @throws InvalidConfigException If the table for the AR class does not exist.
     *
     * @return TableSchemaInterface The schema information of the DB table associated with this AR class.
     */
    public function getTableSchema(): TableSchemaInterface
    {
        $tableSchema = $this->db->getSchema()->getTableSchema(static::tableName());

        if ($tableSchema === null) {
            throw new InvalidConfigException('The table does not exist: ' . static::tableName());
        }

        return $tableSchema;
    }

    public function insert(array $attributes = null): bool
    {
        if (!$this->isTransactional(self::OP_INSERT)) {
            return $this->insertInternal($attributes);
        }

        $transaction = $this->db->beginTransaction();

        try {
            $result = $this->insertInternal($attributes);
            if ($result === false) {
                $transaction->rollBack();
            } else {
                $transaction->commit();
            }

            return $result;
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Returns a value indicating whether the specified operation is transactional.
     *
     * @param int $operation The operation to check. Possible values are {@see OP_INSERT}, {@see OP_UPDATE} and
     * {@see OP_DELETE}.
     *
     * @return array|bool Whether the specified operation is transactional.
     */
    public function isTransactional(int $operation): array|bool
    {
        return $this->transactions();
    }

    /**
     * Loads default values from database table schema.
     *
     * You may call this method to load default values after creating a new instance:
     *
     * ```php
     * // class Customer extends ActiveRecord
     * $customer = new Customer($db);
     * $customer->loadDefaultValues();
     * ```
     *
     * @param bool $skipIfSet Whether existing value should be preserved. This will only set defaults for attributes
     * that are `null`.
     *
     * @throws Exception
     * @throws InvalidConfigException
     *
     * @return self The active record instance itself.
     */
    public function loadDefaultValues(bool $skipIfSet = true): self
    {
        foreach ($this->getTableSchema()->getColumns() as $column) {
            if ($column->getDefaultValue() !== null && (!$skipIfSet || $this->{$column->getName()} === null)) {
                $this->{$column->getName()} = $column->getDefaultValue();
            }
        }

        return $this;
    }

    /**
     * Populates an active record object using a row of data from the database/storage.
     *
     * This is an internal method meant to be called to create active record objects after fetching data from the
     * database. It is mainly used by {@see ActiveQuery} to populate the query results into active records.
     *
     * @param array|object $row Attribute values (name => value).
     *
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function populateRecord($row): void
    {
        $columns = $this->getTableSchema()->getColumns();

        foreach ($row as $name => $value) {
            if (isset($columns[$name])) {
                $row[$name] = $columns[$name]->phpTypecast($value);
            }
        }

        parent::populateRecord($row);
    }

    public function primaryKey(): array
    {
        return $this->getTableSchema()->getPrimaryKey();
    }

    public function refresh(): bool
    {
        $query = $this->instantiateQuery(static::class);

        $tableName = key($query->getTablesUsedInFrom());
        $pk = [];

        /** disambiguate column names in case ActiveQuery adds a JOIN */
        foreach ($this->getPrimaryKey(true) as $key => $value) {
            $pk[$tableName . '.' . $key] = $value;
        }

        $query->where($pk);

        /** @var $record BaseActiveRecord */
        $record = $query->one();

        return $this->refreshInternal($record);
    }

    /**
     * Declares which DB operations should be performed within a transaction in different scenarios.
     *
     * The supported DB operations are: {@see OP_INSERT}, {@see OP_UPDATE} and {@see OP_DELETE}, which correspond to the
     * {@see insert()}, {@see update()} and {@see delete()} methods, respectively.
     *
     * By default, these methods are NOT enclosed in a DB transaction.
     *
     * In some scenarios, to ensure data consistency, you may want to enclose some or all of them in transactions. You
     * can do so by overriding this method and returning the operations that need to be transactional. For example,
     *
     * ```php
     * return [
     *     'admin' => self::OP_INSERT,
     *     'api' => self::OP_INSERT | self::OP_UPDATE | self::OP_DELETE,
     *     // the above is equivalent to the following:
     *     // 'api' => self::OP_ALL,
     *
     * ];
     * ```
     *
     * The above declaration specifies that in the "admin" scenario, the insert operation ({@see insert()}) should be
     * done in a transaction; and in the "api" scenario, all the operations should be done in a transaction.
     *
     * @return array The declarations of transactional operations. The array keys are scenarios names, and the array
     * values are the corresponding transaction operations.
     */
    public function transactions(): array
    {
        return [];
    }

    public function update(array $attributeNames = null): bool|int
    {
        if (!$this->isTransactional(self::OP_UPDATE)) {
            return $this->updateInternal($attributeNames);
        }

        $transaction = $this->db->beginTransaction();

        try {
            $result = $this->updateInternal($attributeNames);
            if ($result === false) {
                $transaction->rollBack();
            } else {
                $transaction->commit();
            }

            return $result;
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    public function updateAll(array $attributes, $condition = [], array $params = []): int
    {
        $command = $this->db->createCommand();

        $command->update(static::tableName(), $attributes, $condition, $params);

        return $command->execute();
    }

    /**
     * Updates the whole table using the provided counter changes and conditions.
     *
     * For example, to increment all customers' age by 1,
     *
     * ```php
     * $customer = new Customer($db);
     * $customer->updateAllCounters(['age' => 1]);
     * ```
     *
     * Note that this method will not trigger any events.
     *
     * @param array $counters The counters to be updated (attribute name => increment value).
     * Use negative values if you want to decrement the counters.
     * @param array|string $condition The conditions that will be put in the WHERE part of the UPDATE SQL. Please refer
     * to {@see Query::where()} on how to specify this parameter.
     * @param array $params The parameters (name => value) to be bound to the query.
     *
     * Do not name the parameters as `:bp0`, `:bp1`, etc., because they are used internally by this method.
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     *
     * @return int The number of rows updated.
     */
    public function updateAllCounters(array $counters, $condition = '', array $params = []): int
    {
        $n = 0;

        foreach ($counters as $name => $value) {
            $counters[$name] = new Expression("[[$name]]+:bp{$n}", [":bp{$n}" => $value]);
            $n++;
        }

        $command = $this->db->createCommand();
        $command->update(static::tableName(), $counters, $condition, $params);

        return $command->execute();
    }

    public static function tableName(): string
    {
        $inflector = new Inflector();

        return '{{%' . $inflector->pascalCaseToId(StringHelper::baseName(static::class), '_') . '}}';
    }

    /**
     * Deletes an ActiveRecord without considering transaction.
     *
     * Note that it is possible the number of rows deleted is 0, even though the deletion execution is successful.
     *
     * @throws Exception
     * @throws StaleObjectException
     * @throws Throwable
     *
     * @return false|int The number of rows deleted, or `false` if the deletion is unsuccessful for some reason.
     */
    protected function deleteInternal(): false|int
    {
        /**
         * We do not check the return value of deleteAll() because it's possible the record is already deleted in the
         * database and thus the method will return 0.
         */
        $condition = $this->getOldPrimaryKey(true);

        $lock = $this->optimisticLock();

        if ($lock !== null) {
            $condition[$lock] = $this->$lock;
        }

        $result = $this->deleteAll($condition);

        if ($lock !== null && !$result) {
            throw new StaleObjectException('The object being deleted is outdated.');
        }

        $this->setOldAttributes(null);

        return $result;
    }

    /**
     * Valid column names are table column names or column names prefixed with table name or table alias.
     *
     * @throws Exception
     * @throws InvalidConfigException
     */
    protected function filterValidColumnNames(array $aliases): array
    {
        $columnNames = [];
        $tableName = static::tableName();
        $quotedTableName = $this->db->getQuoter()->quoteTableName($tableName);

        foreach ($this->getTableSchema()->getColumnNames() as $columnName) {
            $columnNames[] = $columnName;
            $columnNames[] = $this->db->getQuoter()->quoteColumnName($columnName);
            $columnNames[] = "$tableName.$columnName";
            $columnNames[] = $this->db->getQuoter()->quoteSql("$quotedTableName.[[$columnName]]");

            foreach ($aliases as $tableAlias) {
                $columnNames[] = "$tableAlias.$columnName";
                $quotedTableAlias = $this->db->getQuoter()->quoteTableName($tableAlias);
                $columnNames[] = $this->db->getQuoter()->quoteSql("$quotedTableAlias.[[$columnName]]");
            }
        }

        return $columnNames;
    }

    /**
     * Inserts an ActiveRecord into DB without considering transaction.
     *
     * @param array|null $attributes List of attributes that need to be saved. Defaults to `null`, meaning all
     * attributes that are loaded from DB will be saved.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     *
     * @return bool Whether the record is inserted successfully.
     */
    protected function insertInternal(array $attributes = null): bool
    {
        $values = $this->getDirtyAttributes($attributes);

        if (($primaryKeys = $this->db->createCommand()->insertEx(static::tableName(), $values)) === false) {
            return false;
        }

        foreach ($primaryKeys as $name => $value) {
            $id = $this->getTableSchema()->getColumn($name)?->phpTypecast($value);
            $this->setAttribute($name, $id);
            $values[$name] = $id;
        }

        $this->setOldAttributes($values);

        return true;
    }
}
