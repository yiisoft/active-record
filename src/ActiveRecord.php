<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Throwable;
use Yiisoft\Db\Constant\ColumnType;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Schema\TableSchemaInterface;

use function array_diff;
use function array_keys;
use function array_map;
use function array_values;
use function get_object_vars;
use function in_array;
use function is_array;
use function is_string;
use function key;
use function preg_replace;

/**
 * Active Record class which implements {@see ActiveRecordInterface} interface with the minimum set of methods.
 *
 * Active Record implements the [Active Record design pattern](https://en.wikipedia.org/wiki/Active_record).
 *
 * The premise behind Active Record is that an individual {@see ActiveRecord} object is associated with a specific row
 * in a database table. The object's properties are mapped to the columns of the corresponding table.
 *
 * Referencing an Active Record property is equivalent to accessing the corresponding table column for that record.
 *
 * As an example, say that the `Customer` ActiveRecord class is associated with the `customer` table.
 *
 * This would mean that the class's `name` property is automatically mapped to the `name` column in `customer` table.
 * Thanks to Active Record, assuming the variable `$customer` is an object of type `Customer`, to get the value of the
 * `name` column for the table row, you can use the expression `$customer->name`.
 *
 * In this example, Active Record is providing an object-oriented interface for accessing data stored in the database.
 * But Active Record provides much more functionality than this.
 *
 * To declare an ActiveRecord class, you need to extend {@see ActiveRecord} and implement the `getTableName` method:
 *
 * ```php
 * class Customer extends ActiveRecord
 * {
 *     public static function getTableName(): string
 *     {
 *         return 'customer';
 *     }
 * }
 * ```
 *
 * The `getTableName` method only has to return the name of the database table associated with the class.
 *
 * Class instances are obtained in one of two ways:
 *
 * Using the `new` operator to create a new, empty object.
 * Using a method to fetch an existing record (or records) from the database.
 *
 * Below is an example showing some typical usage of ActiveRecord:
 *
 * ```php
 * $user = new User();
 * $user->name = 'Qiang';
 * $user->save(); // a new row is inserted into user table
 *
 * // the following will retrieve the user 'CeBe' from the database
 * $userQuery = new ActiveQuery(User::class);
 * $user = $userQuery->where(['name' => 'CeBe'])->one();
 *
 * // this will get related records from orders table when relation is defined
 * $orders = $user->orders;
 * ```
 *
 * For more details and usage information on ActiveRecord,
 * {@see the [guide article on ActiveRecord](guide:db-active-record)}
 *
 * @psalm-suppress ClassMustBeFinal
 */
class ActiveRecord extends AbstractActiveRecord
{
    public function propertyNames(): array
    {
        return $this->getTableSchema()->getColumnNames();
    }

    public function columnType(string $propertyName): string
    {
        return $this->getTableSchema()->getColumn($propertyName)?->getType() ?? ColumnType::STRING;
    }

    public function filterCondition(array $condition, array $aliases = []): array
    {
        $result = [];

        $columnNames = $this->filterValidColumnNames($aliases);

        foreach ($condition as $key => $value) {
            if (is_string($key) && !in_array($this->db()->getQuoter()->quoteSql($key), $columnNames, true)) {
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
     * @throws InvalidConfigException If the table for the AR class doesn't exist.
     *
     * @return TableSchemaInterface The schema information of the DB table associated with this AR class.
     */
    public function getTableSchema(): TableSchemaInterface
    {
        $tableSchema = $this->db()->getSchema()->getTableSchema($this->getTableName());

        if ($tableSchema === null) {
            throw new InvalidConfigException('The table does not exist: ' . $this->getTableName());
        }

        return $tableSchema;
    }

    /**
     * Loads default values from database table schema.
     *
     * You may call this method to load default values after creating a new instance:
     *
     * ```php
     * // class Customer extends ActiveRecord
     * $customer = new Customer();
     * $customer->loadDefaultValues();
     * ```
     *
     * @param bool $skipIfSet Whether existing value should be preserved. This will only set defaults for properties
     * that are `null`.
     *
     * @throws Exception
     * @throws InvalidConfigException
     *
     * @return static The active record instance itself.
     */
    public function loadDefaultValues(bool $skipIfSet = true): static
    {
        foreach ($this->getTableSchema()->getColumns() as $name => $column) {
            if ($column->getDefaultValue() !== null && (!$skipIfSet || $this->get($name) === null)) {
                $this->set($name, $column->getDefaultValue());
            }
        }

        return $this;
    }

    public function populateRecord(array|object $row): void
    {
        $row = ArArrayHelper::toArray($row);
        $columns = $this->getTableSchema()->getColumns();
        $rowColumns = array_intersect_key($row, $columns);

        foreach ($rowColumns as $name => &$value) {
            $value = $columns[$name]->phpTypecast($value);
        }

        parent::populateRecord($rowColumns + $row);
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

        /** @var string $tableName */
        $tableName = key($query->getTablesUsedInFrom());
        $pk = [];

        /** disambiguate column names in case ActiveQuery adds a JOIN */
        foreach ($this->getPrimaryKey(true) as $key => $value) {
            $pk[$tableName . '.' . $key] = $value;
        }

        $query->where($pk);

        return $this->refreshInternal($query->one());
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
        $quoter = $this->db()->getQuoter();
        $quotedTableName = $quoter->quoteTableName($tableName);

        foreach ($this->getTableSchema()->getColumnNames() as $columnName) {
            $columnNames[] = $columnName;
            $columnNames[] = $quoter->quoteColumnName($columnName);
            $columnNames[] = "$tableName.$columnName";
            $columnNames[] = $quoter->quoteSql("$quotedTableName.[[$columnName]]");

            foreach ($aliases as $tableAlias) {
                $columnNames[] = "$tableAlias.$columnName";
                $quotedTableAlias = $quoter->quoteTableName($tableAlias);
                $columnNames[] = $quoter->quoteSql("$quotedTableAlias.[[$columnName]]");
            }
        }

        return $columnNames;
    }

    protected function propertyValuesInternal(): array
    {
        return get_object_vars($this);
    }

    protected function insertInternal(array|null $propertyNames = null): bool
    {
        $values = $this->newValues($propertyNames);
        $primaryKeys = $this->db()->createCommand()->insertWithReturningPks($this->getTableName(), $values);

        if ($primaryKeys === false) {
            return false;
        }

        $columns = $this->getTableSchema()->getColumns();

        foreach ($primaryKeys as $name => $value) {
            $id = $columns[$name]->phpTypecast($value);
            $this->set($name, $id);
            $values[$name] = $id;
        }

        $this->assignOldValues($values);

        return true;
    }

    protected function populateProperty(string $name, mixed $value): void
    {
        $this->$name = $value;
    }
}
