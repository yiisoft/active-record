<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Throwable;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Schema\TableSchemaInterface;

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
 * Active Record implements the [Active Record design pattern](https://en.wikipedia.org/wiki/Active_record).
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
        return $this->getTableSchema()->getColumnNames();
    }

    public function delete(): int
    {
        if (!$this->isTransactional(self::OP_DELETE)) {
            return $this->deleteInternal();
        }

        $transaction = $this->db->beginTransaction();

        try {
            $result = $this->deleteInternal();
            $transaction->commit();

            return $result;
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

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
        $tableSchema = $this->db->getSchema()->getTableSchema($this->getTableName());

        if ($tableSchema === null) {
            throw new InvalidConfigException('The table does not exist: ' . $this->getTableName());
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
            if ($column->getDefaultValue() !== null && (!$skipIfSet || $this->getAttribute($column->getName()) === null)) {
                $this->setAttribute($column->getName(), $column->getDefaultValue());
            }
        }

        return $this;
    }

    public function populateRecord(array|object $row): void
    {
        $columns = $this->getTableSchema()->getColumns();

        /** @psalm-var array[][] $row */
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

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws Throwable
     */
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

        return $this->refreshInternal($query->onePopulate());
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

    public function update(array $attributeNames = null): false|int
    {
        if (!$this->isTransactional(self::OP_UPDATE)) {
            return $this->updateInternal($attributeNames);
        }

        $transaction = $this->db->beginTransaction();

        try {
            $result = $this->updateInternal($attributeNames);
            if ($result === 0) {
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
     * Valid column names are table column names or column names prefixed with table name or table alias.
     *
     * @throws Exception
     * @throws InvalidConfigException
     */
    protected function filterValidColumnNames(array $aliases): array
    {
        $columnNames = [];
        $tableName = $this->getTableName();
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
     * @throws Throwable
     *
     * @return bool Whether the record is inserted successfully.
     */
    protected function insertInternal(array $attributes = null): bool
    {
        $values = $this->getDirtyAttributes($attributes);

        if (($primaryKeys = $this->db->createCommand()->insertWithReturningPks($this->getTableName(), $values)) === false) {
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
