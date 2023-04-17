<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Throwable;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\StaleObjectException;

interface ActiveRecordInterface
{
    /**
     * Returns the list of all attribute names of the model.
     *
     * The default implementation will return all column names of the table associated with this AR class.
     *
     * @throws InvalidConfigException
     * @throws Exception
     *
     * @return array List of attribute names.
     *
     * @psalm-return string[]
     */
    public function attributes(): array;

    /**
     * Deletes the table row corresponding to this active record.
     *
     * @throws StaleObjectException If {@see optimisticLock|optimistic locking} is enabled and the data being deleted
     * is outdated.
     * @throws Throwable In case delete failed.
     *
     * @return false|int The number of rows deleted, or `false` if the deletion is unsuccessful for some reason.
     *
     * Note that it is possible the number of rows deleted is 0, even though the deletion execution is successful.
     */
    public function delete(): false|int;

    /**
     * Deletes rows in the table using the provided conditions.
     *
     * For example, to delete all customers whose status is 3:
     *
     * ```php
     * $customer = new Customer($this->db);
     * $customer->deleteAll('status = 3');
     * ```
     *
     * > Warning: If you do not specify any condition, this method will delete **all** rows in the table.
     *
     * ```php
     * $customerQuery = new ActiveQuery(Customer::class, $this->db);
     * $aqClasses = $customerQuery->where('status = 3')->all();
     * foreach ($aqClasses as $aqClass) {
     *     $aqClass->delete();
     * }
     * ```
     *
     * For a large set of models you might consider using {@see ActiveQuery::each()} to keep memory usage within limits.
     *
     * @param array $condition The conditions that will be put in the WHERE part of the DELETE SQL. Please refer to
     * {@see Query::where()} on how to specify this parameter.
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     *
     * @return int The number of rows deleted.
     */
    public function deleteAll(array $condition = []): int;

    /**
     * Returns a value indicating whether the given active record is the same as the current one.
     *
     * The comparison is made by comparing the table names and the primary key values of the two active records. If one
     * of the records {@see isNewRecord|is new} they are also considered not equal.
     *
     * @param self $record Record to compare to.
     *
     * @return bool Whether the two active records refer to the same row in the same database table.
     */
    public function equals(self $record): bool;

    /**
     * @return array The default implementation returns the names of the columns whose values have been populated into
     * this record.
     */
    public function fields(): array;

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
    public function filterCondition(array $condition, array $aliases = []): array;

    /**
     * Returns table aliases which are not the same as the name of the tables.
     *
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     */
    public function filterValidAliases(ActiveQuery $query): array;

    /**
     * Returns the named attribute value.
     *
     * If this record is the result of a query and the attribute is not loaded, `null` will be returned.
     *
     * @param string $name The attribute name.
     *
     * @return mixed The attribute value. `null` if the attribute is not set or does not exist.
     *
     * {@see hasAttribute()}
     */
    public function getAttribute(string $name): mixed;

    /**
     * Returns a value indicating whether the current record is new (not saved in the database).
     *
     * @return bool Whether the record is new and should be inserted when calling {@see save()}.
     */
    public function getIsNewRecord(): bool;

    /**
     * Returns the old primary key value(s).
     *
     * This refers to the primary key value that is populated into the record after executing a find method (e.g.
     * findOne()).
     *
     * The value remains unchanged even if the primary key attribute is manually assigned with a different value.
     *
     * @param bool $asArray Whether to return the primary key value as an array. If true, the return value will be an
     * array with column name as key and column value as value. If this is `false` (default), a scalar value will be
     * returned for non-composite primary key.
     *
     * @return mixed The old primary key value. An array (column name => column value) is returned if the primary key
     * is composite or `$asArray` is true. A string is returned otherwise (`null` will be returned if the key value is
     * `null`).
     */
    public function getOldPrimaryKey(bool $asArray = false): mixed;

    /**
     * Returns the primary key value(s).
     *
     * @param bool $asArray Whether to return the primary key value as an array. If true, the return value will be an
     * array with attribute names as keys and attribute values as values. Note that for composite primary keys, an array
     * will always be returned regardless of this parameter value.
     *
     * @return mixed The primary key value. An array (attribute name => attribute value) is returned if the primary key
     * is composite or `$asArray` is true. A string is returned otherwise (`null` will be returned if the key value is
     * `null`).
     */
    public function getPrimaryKey(bool $asArray = false): mixed;

    /**
     * Returns the relation object with the specified name.
     *
     * A relation is defined by a getter method which returns an object implementing the {@see ActiveQueryInterface}
     * (normally this would be a relational {@see ActiveQuery} object).
     *
     * @param string $name The relation name, e.g. `orders` for a relation defined via `getOrders()` method
     * (case-sensitive).
     * @param bool $throwException Whether to throw exception if the relation does not exist.
     *
     * @return ActiveQueryInterface|null The relational query object.
     */
    public function getRelation(string $name, bool $throwException = true): ActiveQueryInterface|null;

    /**
     * Return the name of the table associated with this AR class.
     *
     * ```php
     * final class User extends ActiveRecord
     * {
     *     public string const TABLE_NAME = 'user';
     *
     *     public function getTableName(): string
     *     {
     *          return self::TABLE_NAME;
     *     }
     * }
     */
    public function getTableName(): string;

    /**
     * Returns a value indicating whether the record has an attribute with the specified name.
     *
     * @param string $name The name of the attribute.
     *
     * @return bool Whether the record has an attribute with the specified name.
     */
    public function hasAttribute(string $name): bool;

    /**
     * Inserts a row into the associated database table using the attribute values of this record.
     *
     * Only the {@see dirtyAttributes|changed attribute values} will be inserted into database.
     *
     * If the table's primary key is auto-incremental and is `null` during insertion, it will be populated with the
     * actual value after insertion.
     *
     * For example, to insert a customer record:
     *
     * ```php
     * $customer = new Customer($db);
     * $customer->name = $name;
     * $customer->email = $email;
     * $customer->insert();
     * ```
     *
     * @param array|null $attributes List of attributes that need to be saved. Defaults to `null`, meaning all
     * attributes that are loaded from DB will be saved.
     *
     * @throws InvalidConfigException
     * @throws Throwable In case insert failed.
     *
     * @return bool Whether the attributes are valid and the record is inserted successfully.
     */
    public function insert(array $attributes = null): bool;

    /**
     * Returns a value indicating whether the given set of attributes represents the primary key for this active record.
     *
     * @param array $keys The set of attributes to check.
     *
     * @return bool whether The given set of attributes represents the primary key for this active record.
     */
    public function isPrimaryKey(array $keys): bool;

    /**
     * Check whether the named relation has been populated with records.
     *
     * @param string $name The relation name, e.g. `orders` for a relation defined via `getOrders()` method
     * (case-sensitive).
     *
     * @return bool Whether relation has been populated with records.
     *
     * {@see getRelation()}
     */
    public function isRelationPopulated(string $name): bool;

    /**
     * Establishes the relationship between two records.
     *
     * The relationship is established by setting the foreign key value(s) in one record to be the corresponding primary
     * key value(s) in the other record.
     *
     * The record with the foreign key will be saved into database without performing validation.
     *
     * If the relationship involves a junction table, a new row will be inserted into the junction table which contains
     * the primary key values from both records.
     *
     * This method requires that the primary key value is not `null`.
     *
     * @param string $name The case-sensitive name of the relationship, e.g. `orders` for a relation defined via
     * `getOrders()` method.
     * @param self $arClass The record to be linked with the current one.
     * @param array $extraColumns Additional column values to be saved into the junction table. This parameter is only
     * meaningful for a relationship involving a junction table (i.e., a relation set with
     * {@see ActiveQueryInterface::via()}).
     */
    public function link(string $name, self $arClass, array $extraColumns = []): void;

    /**
     * Returns the element at the specified offset.
     *
     * This method is required by the SPL interface {@see ArrayAccess}.
     *
     * It is implicitly called when you use something like `$value = $model[$offset];`.
     *
     * @param mixed $offset the offset to retrieve element.
     *
     * @return mixed the element at the offset, null if no element is found at the offset
     */
    public function offsetGet(mixed $offset): mixed;

    /**
     * Populates the named relation with the related records.
     *
     * Note that this method does not check if the relation exists or not.
     *
     * @param string $name The relation name, e.g. `orders` for a relation defined via `getOrders()` method
     * (case-sensitive).
     * @param array|self|null $records The related records to be populated into the relation.
     */
    public function populateRelation(string $name, array|self|null $records): void;

    /**
     * Returns the primary key name(s) for this AR class.
     *
     * The default implementation will return the primary key(s) as declared in the DB table that is associated with
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
     * @psalm-return string[] The primary keys of the associated database table.
     */
    public function primaryKey(): array;

    /**
     * Saves the current record.
     *
     * This method will call {@see insert()} when {@see getIsNewRecord()|isNewRecord} is true, or {@see update()} when
     * {@see getIsNewRecord()|isNewRecord} is false.
     *
     * For example, to save a customer record:
     *
     * ```php
     * $customer = new Customer($db);
     * $customer->name = $name;
     * $customer->email = $email;
     * $customer->save();
     * ```
     *
     * @param array|null $attributeNames List of attribute names that need to be saved. Defaults to `null`,
     * meaning all attributes that are loaded from DB will be saved.
     *
     * @return bool Whether the saving succeeded (i.e. no validation errors occurred).
     */
    public function save(array $attributeNames = null): bool;

    /**
     * Sets the named attribute value.
     *
     * @param string $name The attribute name.
     * @param mixed $value The attribute value.
     *
     * @throws InvalidArgumentException If the named attribute does not exist.
     */
    public function setAttribute(string $name, mixed $value): void;

    /**
     * Saves the changes to this active record into the associated database table.
     *
     * Only the {@see dirtyAttributes|changed attribute values} will be saved into database.
     *
     * For example, to update a customer record:
     *
     * ```php
     * $customer = new Customer($db);
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
     * @param array|null $attributeNames List of attributes that need to be saved. Defaults to `null`, meaning all
     * attributes that are loaded from DB will be saved.
     *
     * @throws StaleObjectException If {@see optimisticLock|optimistic locking} is enabled and the data being updated is
     * outdated.
     * @throws Throwable In case update failed.
     *
     * @return false|int The number of rows affected, or false if validation fails or {@seebeforeSave()} stops the
     * updating process.
     */
    public function update(array $attributeNames = null): false|int;

    /**
     * Updates the whole table using the provided attribute values and conditions.
     *
     * For example, to change the status to be 1 for all customers whose status is 2:
     *
     * ```php
     * $customer = new Customer($db);
     * $customer->updateAll(['status' => 1], 'status = 2');
     * ```
     *
     * > Warning: If you do not specify any condition, this method will update **all** rows in the table.
     *
     * ```php
     * $customerQuery = new ActiveQuery(Customer::class, $db);
     * $aqClasses = $customerQuery->where('status = 2')->all();
     * foreach ($aqClasses as $aqClass) {
     *     $aqClass->status = 1;
     *     $aqClass->update();
     * }
     * ```
     *
     * For a large set of models you might consider using {@see ActiveQuery::each()} to keep memory usage within limits.
     *
     * @param array $attributes Attribute values (name-value pairs) to be saved into the table.
     * @param array|string $condition The conditions that will be put in the WHERE part of the UPDATE SQL.
     * Please refer to {@see Query::where()} on how to specify this parameter.
     * @param array $params The parameters (name => value) to be bound to the query.
     *
     * @throws InvalidConfigException
     * @throws Throwable if the models cannot be unlinked.
     * @throws Exception
     *
     * @return int The number of rows updated.
     */
    public function updateAll(array $attributes, array|string $condition = [], array $params = []): int;

    /**
     * Destroys the relationship between two records.
     *
     * The record with the foreign key of the relationship will be deleted if `$delete` is true.
     *
     * Otherwise, the foreign key will be set `null` and the record will be saved without validation.
     *
     * @param string $name The case-sensitive name of the relationship, e.g. `orders` for a relation defined via
     * `getOrders()` method.
     * @param self $arClass The active record to be unlinked from the current one.
     * @param bool $delete Whether to delete the active record that contains the foreign key.
     * If false, the active record's foreign key will be set `null` and saved.
     * If true, the active record containing the foreign key will be deleted.
     */
    public function unlink(string $name, self $arClass, bool $delete = false): void;

    /**
     * Returns the old attribute values.
     *
     * @return array The old attribute values (name-value pairs).
     */
    public function getOldAttributes(): array;

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
    public function populateRecord(array|object $row): void;

    /**
     * Serializes the active record into its array implementation with attribute name as key and it value as... value.
     */
    public function toArray(): array;
}
