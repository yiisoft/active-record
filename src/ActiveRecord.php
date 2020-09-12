<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Throwable;
use Yiisoft\Arrays\ArrayHelper;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Exception\StaleObjectException;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Schema\TableSchema;
use Yiisoft\Strings\Inflector;
use Yiisoft\Strings\StringHelper;

use function array_diff;
use function array_fill_keys;
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
 * The premise behind Active Record is that an individual [[ActiveRecord]] object is associated with a specific row in a
 * database table. The object's attributes are mapped to the columns of the corresponding table.
 *
 * Referencing an Active Record attribute is equivalent to accessing the corresponding table column for that record.
 *
 * As an example, say that the `Customer` ActiveRecord class is associated with the `customer` table.
 *
 * This would mean that the class's `name` attribute is automatically mapped to the `name` column in `customer` table.
 * Thanks to Active Record, assuming the variable `$customer` is an object of type `Customer`, to get the value of
 * the `name` column for the table row, you can use the expression `$customer->name`.
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
 *     public static function tableName()
 *     {
 *         return 'customer';
 *     }
 * }
 * ```
 *
 * The `tableName` method only has to return the name of the database table associated with the class.
 *
 * > Tip: You may also use the [Gii code generator](guide:start-gii) to generate ActiveRecord classes from your
 * > database tables.
 *
 * Class instances are obtained in one of two ways:
 *
 * * Using the `new` operator to create a new, empty object
 * * Using a method to fetch an existing record (or records) from the database
 *
 * Below is an example showing some typical usage of ActiveRecord:
 *
 * ```php
 * $user = new User();
 * $user->name = 'Qiang';
 * $user->save();  // a new row is inserted into user table
 *
 * // the following will retrieve the user 'CeBe' from the database
 * $user = User::find()->where(['name' => 'CeBe'])->one();
 *
 * // this will get related records from orders table when relation is defined
 * $orders = $user->orders;
 * ```
 *
 * For more details and usage information on ActiveRecord,
 * see the [guide article on ActiveRecord](guide:db-active-record).
 *
 * @method ActiveQuery hasMany($class, array $link) see {@see BaseActiveRecord::hasMany()} for more info
 * @method ActiveQuery hasOne($class, array $link) see {@see BaseActiveRecord::hasOne()} for more info
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
     * @param bool $skipIfSet whether existing value should be preserved. This will only set defaults for attributes
     * that are `null`.
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     *
     * @return $this the model instance itself.
     */
    public function loadDefaultValues(bool $skipIfSet = true): self
    {
        foreach (static::getTableSchema()->getColumns() as $column) {
            if ($column->getDefaultValue() !== null && (!$skipIfSet || $this->{$column->getName()} === null)) {
                $this->{$column->getName()} = $column->getDefaultValue();
            }
        }

        return $this;
    }

    /**
     * Creates an {@see ActiveQuery} instance with a given SQL statement.
     *
     * Note that because the SQL statement is already specified, calling additional query modification methods
     * (such as `where()`, `order()`) on the created {@see ActiveQuery} instance will have no effect. However, calling
     * `with()`, `asArray()` or `indexBy()` is still fine.
     *
     * Below is an example:
     *
     * ```php
     * $customers = Customer::findBySql('SELECT * FROM customer')->all();
     * ```
     *
     * @param string $sql the SQL statement to be executed
     * @param array $params parameters to be bound to the SQL statement during execution.
     *
     * @return Query the newly created {@see ActiveQuery} instance
     */
    public static function findBySql(string $sql, array $params = []): Query
    {
        return (static::find())->sql($sql)->params($params);
    }

    /**
     * Finds ActiveRecord instance(s) by the given condition.
     *
     * This method is internally called by {@see findOne()} and {@see findAll()}.
     *
     * @param mixed $condition please refer to {@see findOne()} for the explanation of this parameter.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException if there is no primary key defined.
     * @throws NotSupportedException
     *
     * @return ActiveQueryInterface the newly created {@see ActiveQueryInterface|ActiveQuery} instance.
     */
    protected static function findByCondition($condition): ActiveQueryInterface
    {
        $query = static::find();

        if (!is_array($condition)) {
            $condition = [$condition];
        }

        if (!ArrayHelper::isAssociative($condition)) {
            /** query by primary key */
            $primaryKey = static::primaryKey();

            if (isset($primaryKey[0])) {
                $pk = $primaryKey[0];

                if (!empty($query->getJoin()) || !empty($query->getJoinWith())) {
                    $pk = static::tableName() . '.' . $pk;
                }

                /**
                 * if condition is scalar, search for a single primary key, if it is array, search for multiple primary
                 * key values
                 */
                $condition = [$pk => is_array($condition) ? array_values($condition) : $condition];
            } else {
                throw new InvalidConfigException('"' . static::class . '" must have a primary key.');
            }
        } elseif (is_array($condition)) {
            $aliases = static::filterValidAliases($query);
            $condition = static::filterCondition($condition, $aliases);
        }

        return $query->andWhere($condition);
    }

    /**
     * Returns table aliases which are not the same as the name of the tables.
     *
     * @param ActiveQuery $query
     *
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     *
     * @return array
     */
    protected static function filterValidAliases(ActiveQuery $query): array
    {
        $tables = $query->getTablesUsedInFrom();

        $aliases = array_diff(array_keys($tables), $tables);

        return array_map(static function ($alias) {
            return preg_replace('/{{([\w]+)}}/', '$1', $alias);
        }, array_values($aliases));
    }

    /**
     * Filters array condition before it is assigned to a Query filter.
     *
     * This method will ensure that an array condition only filters on existing table columns.
     *
     * @param array $condition condition to filter.
     * @param array $aliases
     *
     * @throws Exception
     * @throws InvalidArgumentException in case array contains unsafe values.
     * @throws InvalidConfigException
     * @throws NotSupportedException
     *
     * @return array filtered condition.
     */
    protected static function filterCondition(array $condition, array $aliases = []): array
    {
        $result = [];

        $columnNames = static::filterValidColumnNames($aliases);

        foreach ($condition as $key => $value) {
            if (is_string($key) && !in_array(static::getConnection()->quoteSql($key), $columnNames, true)) {
                throw new InvalidArgumentException(
                    'Key "' . $key . '" is not a column name and can not be used as a filter'
                );
            }
            $result[$key] = is_array($value) ? array_values($value) : $value;
        }

        return $result;
    }

    /**
     * Valid column names are table column names or column names prefixed with table name or table alias.
     *
     * @param array $aliases
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     *
     * @return array
     */
    protected static function filterValidColumnNames(array $aliases): array
    {
        $columnNames = [];
        $tableName = static::tableName();
        $quotedTableName = static::getConnection()->quoteTableName($tableName);

        foreach (static::getTableSchema()->getColumnNames() as $columnName) {
            $columnNames[] = $columnName;
            $columnNames[] = static::getConnection()->quoteColumnName($columnName);
            $columnNames[] = "$tableName.$columnName";
            $columnNames[] = static::getConnection()->quoteSql("$quotedTableName.[[$columnName]]");

            foreach ($aliases as $tableAlias) {
                $columnNames[] = "$tableAlias.$columnName";
                $quotedTableAlias = static::getConnection()->quoteTableName($tableAlias);
                $columnNames[] = static::getConnection()->quoteSql("$quotedTableAlias.[[$columnName]]");
            }
        }

        return $columnNames;
    }

    public function refresh(): bool
    {
        $query = self::find();

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
     * Updates the whole table using the provided attribute values and conditions.
     *
     * For example, to change the status to be 1 for all customers whose status is 2:
     *
     * ```php
     * Customer::updateAll(['status' => 1], 'status = 2');
     * ```
     *
     * > Warning: If you do not specify any condition, this method will update **all** rows in the table.
     *
     * Note that this method will not trigger any events. If you need {@see EVENT_BEFORE_UPDATE} or
     * {@see EVENT_AFTER_UPDATE} to be triggered, you need to {@see find()|find} the models first and then call
     * {@see update()} on each of them. For example an equivalent of the example above would be:
     *
     * ```php
     * $models = Customer::find()->where('status = 2')->all();
     * foreach ($models as $model) {
     *     $model->status = 1;
     *     $model->update(false); // skipping validation as no user input is involved
     * }
     * ```
     *
     * For a large set of models you might consider using {@see ActiveQuery::each()} to keep memory usage within limits.
     *
     * @param array $attributes attribute values (name-value pairs) to be saved into the table.
     * @param array|string $condition the conditions that will be put in the WHERE part of the UPDATE SQL.
     * Please refer to {@see Query::where()} on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query.
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws Throwable
     *
     * @return int the number of rows updated.
     */
    public static function updateAll(array $attributes, $condition = '', array $params = []): int
    {
        $command = static::getConnection()->createCommand();

        $command->update(static::tableName(), $attributes, $condition, $params);

        return $command->execute();
    }

    /**
     * Updates the whole table using the provided counter changes and conditions.
     *
     * For example, to increment all customers' age by 1,
     *
     * ```php
     * Customer::updateAllCounters(['age' => 1]);
     * ```
     *
     * Note that this method will not trigger any events.
     *
     * @param array $counters the counters to be updated (attribute name => increment value).
     * Use negative values if you want to decrement the counters.
     * @param string|array $condition the conditions that will be put in the WHERE part of the UPDATE SQL.
     * Please refer to {@see Query::where()} on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query.
     *
     * Do not name the parameters as `:bp0`, `:bp1`, etc., because they are used internally by this method.
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws Throwable
     *
     * @return int the number of rows updated.
     */
    public static function updateAllCounters(array $counters, $condition = '', array $params = []): int
    {
        $n = 0;

        foreach ($counters as $name => $value) {
            $counters[$name] = new Expression("[[$name]]+:bp{$n}", [":bp{$n}" => $value]);
            $n++;
        }

        $command = static::getConnection()->createCommand();
        $command->update(static::tableName(), $counters, $condition, $params);

        return $command->execute();
    }

    /**
     * Deletes rows in the table using the provided conditions.
     *
     * For example, to delete all customers whose status is 3:
     *
     * ```php
     * Customer::deleteAll('status = 3');
     * ```
     *
     * > Warning: If you do not specify any condition, this method will delete **all** rows in the table.
     *
     * Note that this method will not trigger any events. If you need {@see EVENT_BEFORE_DELETE} or
     * {@see EVENT_AFTER_DELETE} to be triggered, you need to {@see find()|find} the models first and then
     * call {@see delete()} on each of them. For example an equivalent of the example above would be:
     *
     * ```php
     * $models = Customer::find()->where('status = 3')->all();
     * foreach ($models as $model) {
     *     $model->delete();
     * }
     * ```
     *
     * For a large set of models you might consider using {@see ActiveQuery::each()} to keep memory usage within limits.
     *
     * @param array|null $condition the conditions that will be put in the WHERE part of the DELETE SQL. Please refer
     * to {@see Query::where()} on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query.
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws Throwable
     *
     * @return int the number of rows deleted.*
     */
    public static function deleteAll(?array $condition = null, array $params = []): int
    {
        $command = static::getConnection()->createCommand();
        $command->delete(static::tableName(), $condition, $params);

        return $command->execute();
    }

    /**
     * @return ActiveQuery the newly created {@see ActiveQuery} instance.
     */
    public static function find(): ActiveQuery
    {
        return new ActiveQuery(static::class);
    }

    /**
     * Declares the name of the database table associated with this AR class.
     *
     * By default this method returns the class name as the table name by calling {@see Inflector::pascalCaseToId()}
     * with prefix {@see Connection::tablePrefix}. For example if {@see Connection::tablePrefix} is `tbl_`, `Customer`
     * becomes `tbl_customer`, and `OrderItem` becomes `tbl_order_item`. You may override this method if the table is
     * not named after this convention.
     *
     * @return string the table name.
     */
    public static function tableName(): string
    {
        return '{{%' . (new Inflector())->pascalCaseToId(StringHelper::baseName(static::class), '_') . '}}';
    }

    /**
     * Returns the schema information of the DB table associated with this AR class.
     *
     * @throws Exception
     * @throws InvalidConfigException if the table for the AR class does not exist.
     *
     * @return TableSchema the schema information of the DB table associated with this AR class.
     */
    public static function getTableSchema(): TableSchema
    {
        $tableSchema = static::getConnection()
            ->getSchema()
            ->getTableSchema(static::tableName());

        if ($tableSchema === null) {
            throw new InvalidConfigException('The table does not exist: ' . static::tableName());
        }

        return $tableSchema;
    }

    /**
     * Returns the primary key name(s) for this AR class.
     *
     * The default implementation will return the primary key(s) as declared  in the DB table that is associated with
     * this AR class.
     *
     * If the DB table does not declare any primary key, you should override this method to return the attributes that
     * you want to use as primary keys for this AR class.
     *
     * Note that an array should be returned even for a table with single primary key.
     *
     * @throws Exception
     * @throws InvalidConfigException
     *
     * @return string[] the primary keys of the associated database table.
     */
    public static function primaryKey(): array
    {
        return static::getTableSchema()->getPrimaryKey();
    }

    /**
     * Returns the list of all attribute names of the model.
     *
     * The default implementation will return all column names of the table associated with this AR class.
     *
     * @return array list of attribute names.
     *
     * @throws InvalidConfigException
     *
     * @throws Exception
     */
    public function attributes(): array
    {
        return array_keys(static::getTableSchema()->getColumns());
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
     * @return array the declarations of transactional operations. The array keys are scenarios names, and the array
     * values are the corresponding transaction operations.
     */
    public function transactions(): array
    {
        return [];
    }

    /**
     * Populates an active record object using a row of data from the database/storage.
     *
     * This is an internal method meant to be called to create active record objects after fetching data from the
     * database. It is mainly used by {@see ActiveQuery} to populate the query results into active records.
     *
     * When calling this method manually you should call {@see afterFind()} on the created record to trigger the
     * {@see EVENT_AFTER_FIND|afterFind Event}.
     *
     * @param BaseActiveRecord|array $record the record to be populated. In most cases this will be an instance created by
     * {@see instantiate()} beforehand.
     * @param array|object $row attribute values (name => value).
     *
     * @throws Exception
     * @throws InvalidConfigException
     */
    public static function populateRecord($record, $row): void
    {
        $columns = static::getTableSchema()->getColumns();

        foreach ($row as $name => $value) {
            if (isset($columns[$name])) {
                $row[$name] = $columns[$name]->phpTypecast($value);
            }
        }

        parent::populateRecord($record, $row);
    }

    /**
     * Inserts a row into the associated database table using the attribute values of this record.
     *
     * This method performs the following steps in order:
     *
     * 1. call {@see beforeValidate()} when `$runValidation` is `true`. If {@see beforeValidate()} returns `false`, the
     * rest of the steps will be skipped.
     * 2. call {@see afterValidate()} when `$runValidation` is `true`. If validation failed, the rest of the steps will
     * be skipped.
     * 3. call {@see beforeSave()}. If {@see beforeSave()} returns `false`, the rest of the steps will be skipped.
     * 4. insert the record into database. If this fails, it will skip the rest of the steps.
     * 5. call {@see afterSave()}.
     *
     * In the above step 1, 2, 3 and 5, events {@see EVENT_BEFORE_VALIDATE}, {@see EVENT_AFTER_VALIDATE},
     * {@see EVENT_BEFORE_INSERT}, and {@see EVENT_AFTER_INSERT} will be raised by the corresponding methods.
     *
     * Only the {@see dirtyAttributes|changed attribute values} will be inserted into database.
     *
     * If the table's primary key is auto-incremental and is `null` during insertion, it will be populated with the
     * actual value after insertion.
     *
     * For example, to insert a customer record:
     *
     * ```php
     * $customer = new Customer;
     * $customer->name = $name;
     * $customer->email = $email;
     * $customer->insert();
     * ```
     *
     * @param array|null $attributes list of attributes that need to be saved. Defaults to `null`, meaning all
     * attributes that are loaded from DB will be saved.
     *
     * @throws InvalidConfigException
     * @throws Throwable in case insert failed.
     *
     * @return bool whether the attributes are valid and the record is inserted successfully.
     */
    public function insert(array $attributes = null): bool
    {
        if (!$this->isTransactional(self::OP_INSERT)) {
            return $this->insertInternal($attributes);
        }

        $transaction = static::getConnection()->beginTransaction();

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
     * Inserts an ActiveRecord into DB without considering transaction.
     *
     * @param array|null $attributes list of attributes that need to be saved. Defaults to `null`, meaning all
     * attributes that are loaded from DB will be saved.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     *
     * @return bool whether the record is inserted successfully.
     */
    protected function insertInternal(?array $attributes = null): bool
    {
        $values = $this->getDirtyAttributes($attributes);

        if (($primaryKeys = static::getConnection()->getSchema()->insert(static::tableName(), $values)) === false) {
            return false;
        }

        foreach ($primaryKeys as $name => $value) {
            $id = static::getTableSchema()->getColumn($name)->phpTypecast($value);
            $this->setAttribute($name, $id);
            $values[$name] = $id;
        }

        $changedAttributes = array_fill_keys(array_keys($values), null);

        $this->setOldAttributes($values);

        return true;
    }

    /**
     * Saves the changes to this active record into the associated database table.
     *
     * This method performs the following steps in order:
     *
     * 1. call {@see beforeValidate()} when `$runValidation` is `true`. If {@see beforeValidate()} returns `false`, the
     * rest of the steps will be skipped.
     * 2. call {@see afterValidate()} when `$runValidation` is `true`. If validation failed, the rest of the steps will
     * be skipped.
     * 3. call {@see beforeSave()}. If {@see beforeSave()} returns `false`, the rest of the steps will be skipped.
     * 4. save the record into database. If this fails, it will skip the rest of the steps.
     * 5. call {@see afterSave()}.
     *
     * In the above step 1, 2, 3 and 5, events {@see EVENT_BEFORE_VALIDATE}, {@see EVENT_AFTER_VALIDATE},
     * {@see EVENT_BEFORE_UPDATE}, and {@see EVENT_AFTER_UPDATE} will be raised by the corresponding methods.
     *
     * Only the {@see dirtyAttributes|changed attribute values} will be saved into database.
     *
     * For example, to update a customer record:
     *
     * ```php
     * $customer = Customer::findOne($id);
     * $customer->name = $name;
     * $customer->email = $email;
     * $customer->update();
     * ```
     *
     * Note that it is possible the update does not affect any row in the table. In this case, this method will return
     * 0. For this reason, you should use the following code to check if update() is successful or not:
     *
     * ```php
     * if ($customer->update() !== false) {
     *     // update successful
     * } else {
     *     // update failed
     * }
     * ```
     *
     * @param array|null $attributeNames list of attributes that need to be saved. Defaults to `null`, meaning all
     * attributes that are loaded from DB will be saved.
     *
     * @throws StaleObjectException if {@see optimisticLock|optimistic locking} is enabled and the data being updated is
     * outdated.
     * @throws Throwable in case update failed.
     *
     * @return bool|int the number of rows affected, or false if validation fails or {@seebeforeSave()} stops the
     * updating process.
     */
    public function update(?array $attributeNames = null)
    {
        if (!$this->isTransactional(self::OP_UPDATE)) {
            return $this->updateInternal($attributeNames);
        }

        $transaction = static::getConnection()->beginTransaction();

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

    /**
     * Deletes the table row corresponding to this active record.
     *
     * This method performs the following steps in order:
     *
     * 1. call {@see beforeDelete()}. If the method returns `false`, it will skip the rest of the steps;
     * 2. delete the record from the database.
     * 3. call {@see afterDelete()}.
     *
     * In the above step 1 and 3, events named {@see EVENT_BEFORE_DELETE} and {@see EVENT_AFTER_DELETE} will be raised
     * by the corresponding methods.
     *
     * @throws StaleObjectException if {@see optimisticLock|optimistic locking} is enabled and the data being deleted
     * is outdated.
     * @throws Throwable in case delete failed.
     *
     * @return int|false the number of rows deleted, or `false` if the deletion is unsuccessful for some reason.
     *
     * Note that it is possible the number of rows deleted is 0, even though the deletion execution is successful.
     */
    public function delete()
    {
        if (!$this->isTransactional(self::OP_DELETE)) {
            return $this->deleteInternal();
        }

        $transaction = static::getConnection()->beginTransaction();

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

    /**
     * Deletes an ActiveRecord without considering transaction.
     *
     * Note that it is possible the number of rows deleted is 0, even though the deletion execution is successful.
     *
     * @throws Exception
     * @throws Throwable
     * @throws StaleObjectException
     *
     * @return int|false the number of rows deleted, or `false` if the deletion is unsuccessful for some reason.
     */
    protected function deleteInternal()
    {
        /** add event before delete */

        /**
         * we do not check the return value of deleteAll() because it's possible the record is already deleted in the
         * database and thus the method will return 0.
         */
        $condition = $this->getOldPrimaryKey(true);

        $lock = $this->optimisticLock();

        if ($lock !== null) {
            $condition[$lock] = $this->$lock;
        }

        $result = static::deleteAll($condition);

        if ($lock !== null && !$result) {
            throw new StaleObjectException('The object being deleted is outdated.');
        }

        $this->setOldAttributes(null);

        /** add event after delete */

        return $result;
    }

    /**
     * Returns a value indicating whether the given active record is the same as the current one.
     *
     * The comparison is made by comparing the table names and the primary key values of the two active records. If one
     * of the records {@see isNewRecord|is new} they are also considered not equal.
     *
     * @param ActiveRecordInterface $record record to compare to.
     *
     * @return bool whether the two active records refer to the same row in the same database table.
     */
    public function equals(ActiveRecordInterface $record): bool
    {
        if ($this->isNewRecord || $record->isNewRecord) {
            return false;
        }

        return static::tableName() === $record->tableName() && $this->getPrimaryKey() === $record->getPrimaryKey();
    }

    /**
     * Returns a value indicating whether the specified operation is transactional in the current {@see $scenario}.
     *
     * @param int $operation the operation to check. Possible values are {@see OP_INSERT}, {@see OP_UPDATE} and
     * {@see OP_DELETE}.
     *
     * @return array|bool whether the specified operation is transactional in the current {@see scenario}.
     */
    public function isTransactional(int $operation)
    {
        return $this->transactions();
    }
}
