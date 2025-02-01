<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Throwable;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Constant\ColumnType;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Exception\StaleObjectException;

interface ActiveRecordInterface
{
    /**
     * Returns the list of property names mapped to column names of the table associated with this active record class.
     *
     * @return array List of property names.
     *
     * @psalm-return string[]
     */
    public function propertyNames(): array;

    /**
     * Returns the abstract type of the property.
     *
     * @psalm-return ColumnType::*
     */
    public function columnType(string $propertyName): string;

    /**
     * Returns the database connection used by the Active Record instance.
     */
    public function db(): ConnectionInterface;

    /**
     * Deletes the table row corresponding to this active record.
     *
     * @throws StaleObjectException If {@see optimisticLock|optimistic locking} is enabled and the data being deleted
     * is outdated.
     * @throws Throwable In case delete failed.
     *
     * @return int The number of rows deleted.
     *
     * Note that it's possible the number of rows deleted is 0, even though the deletion execution is successful.
     */
    public function delete(): int;

    /**
     * Deletes rows in the table using the provided conditions.
     *
     * For example, to delete all customers whose status is 3:
     *
     * ```php
     * $customer = new Customer($db);
     * $customer->deleteAll('status = 3');
     * ```
     *
     * > Warning: If you don't specify any condition, this method will delete **all** rows in the table.
     *
     * ```php
     * $customerQuery = new ActiveQuery(Customer::class);
     * $aqClasses = $customerQuery->where('status = 3')->all();
     * foreach ($aqClasses as $aqClass) {
     *     $aqClass->delete();
     * }
     * ```
     *
     * For a large set of models you might consider using {@see ActiveQuery::each()} to keep memory usage within limits.
     *
     * @param array $condition The conditions that will be put in the `WHERE` part of the `DELETE` SQL. Please refer to
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
     * of the records {@see getIsNewRecord|is new} they're also considered not equal.
     *
     * @param self $record Record to compare to.
     *
     * @return bool Whether the two active records refer to the same row in the same database table.
     */
    public function equals(self $record): bool;

    /**
     * Filters array condition before it's assigned to a Query filter.
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
     * Returns table aliases which are different from the name of the tables.
     *
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     */
    public function filterValidAliases(ActiveQuery $query): array;

    /**
     * Returns the named property value.
     *
     * If this record is the result of a query and the property isn't loaded, `null` will be returned.
     *
     * @param string $propertyName The property name.
     *
     * @return mixed The property value. `null` if the property isn't set or doesn't exist.
     *
     * @see hasProperty()
     */
    public function get(string $propertyName): mixed;

    /**
     * Returns property values.
     *
     * @param array|null $names List of property names whose value needs to be returned. Defaults to `null`, meaning all
     * properties listed in {@see propertyNames()} will be returned.
     * @param array $except List of property names whose value shouldn't be returned.
     *
     * @throws Exception
     * @throws InvalidConfigException
     *
     * @return array Property values (name => value).
     */
    public function propertyValues(array|null $names = null, array $except = []): array;

    /**
     * Returns a value indicating whether the current record is new (not saved in the database).
     *
     * @return bool Whether the record is new and should be inserted when calling {@see save()}.
     */
    public function getIsNewRecord(): bool;

    /**
     * Returns the old primary key value(s).
     *
     * This refers to the primary key value that's populated into the record after executing a find method (for example,
     * `findOne()`).
     *
     * The value remains unchanged even if the primary key property is manually assigned with a different value.
     *
     * @param bool $asArray Whether to return the primary key value as an array. If true, the return value will be an
     * array with property name as key and property value as value. If this is `false` (default), a scalar value will be
     * returned for a non-composite primary key.
     *
     * @return mixed The old primary key value. An array (property name => property value) is returned if the primary
     * key is composite or `$asArray` is true. A string is returned otherwise (`null` will be returned if the key value
     * is `null`).
     *
     * @psalm-return (
     *     $asArray is true
     *     ? array<string, mixed>
     *     : mixed|null
     * )
     */
    public function getOldPrimaryKey(bool $asArray = false): mixed;

    /**
     * Returns the primary key value(s).
     *
     * @param bool $asArray Whether to return the primary key value as an array. If true, the return value will be an
     * array with property names as keys and property values as values. Note that for composite primary keys, an array
     * will always be returned regardless of this parameter value.
     *
     * @return mixed The primary key value. An array (property name => property value) is returned if the primary key
     * is composite or `$asArray` is true. A string is returned otherwise (`null` will be returned if the key value is
     * `null`).
     *
     * @psalm-return (
     *     $asArray is true
     *     ? array<string, mixed>
     *     : mixed|null
     * )
     */
    public function getPrimaryKey(bool $asArray = false): mixed;

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
     * Returns a value indicating whether the record has a property with the specified name.
     *
     * @param string $name The name of the property.
     */
    public function hasProperty(string $name): bool;

    /**
     * Inserts a row into the associated database table using the property values of this record.
     *
     * Only the {@see newValues() changed property values} will be inserted into a database.
     *
     * If the table's primary key is auto incremental and is `null` during insertion, it will be populated with the
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
     * @param array|null $propertyNames List of property names that need to be saved. Defaults to `null`, meaning all
     * changed property values will be saved.
     *
     * @throws InvalidConfigException
     * @throws Throwable In case insert failed.
     *
     * @return bool Whether the record is inserted successfully.
     */
    public function insert(array|null $propertyNames = null): bool;

    /**
     * Checks if any property returned by {@see propertyNames()} method has changed.
     * A new active record instance is considered changed if any property has been set including default values.
     */
    public function isChanged(): bool;

    /**
     * Returns a value indicating whether the given set of property names represents the primary key for this active
     * record.
     *
     * @param array $keys The set of property names to check.
     *
     * @return bool whether The given set of property names represents the primary key for this active record.
     */
    public function isPrimaryKey(array $keys): bool;

    /**
     * Returns whether the named property has been changed using the not identical operator `!==`.
     *
     * @param string $name The name of the property.
     *
     * @return bool Whether the property value has been changed.
     */
    public function isPropertyChanged(string $name): bool;

    /**
     * Returns whether the named property has been changed using the not equal operator `!=`.
     *
     * @param string $name The name of the property.
     *
     * @return bool Whether the property value has been changed non-strictly.
     */
    public function isPropertyChangedNonStrict(string $name): bool;

    /**
     * Check whether the named relation has been populated with records.
     *
     * @param string $name The relation name, for example, `orders` (case-sensitive).
     *
     * @return bool Whether relation has been populated with records.
     *
     * {@see relationQuery()}
     */
    public function isRelationPopulated(string $name): bool;

    /**
     * Establishes the relationship between two records.
     *
     * The relationship is established by setting the foreign key value(s) in one record to be the corresponding primary
     * key value(s) in the other record.
     *
     * The record with the foreign key will be saved into a database without performing validation.
     *
     * If the relationship involves a junction table, a new row will be inserted into the junction table which contains
     * the primary key values from both records.
     *
     * This method requires that the primary key value isn't `null`.
     *
     * @param string $relationName The relation name, for example, `orders` (case-sensitive).
     * @param self $arClass The record to be linked with the current one.
     * @param array $extraColumns More column values to be saved into the junction table. This parameter is only
     * meaningful for a relationship involving a junction table (that's a relation set with
     * {@see ActiveQueryInterface::via()}).
     */
    public function link(string $relationName, self $arClass, array $extraColumns = []): void;

    /**
     * Populates the named relation with the related records.
     *
     * Note that this method doesn't check if the relation exists or not.
     *
     * @param string $name The relation name, for example, `orders` (case-sensitive).
     * @param array|array[]|self|self[]|null $records The related records to be populated into the relation.
     */
    public function populateRelation(string $name, array|self|null $records): void;

    /**
     * Returns the primary key name(s) for this AR class.
     *
     * The default implementation will return the primary key(s) as declared in the DB table that's associated with
     * this AR class.
     *
     * If the DB table doesn't declare any primary key, you should override this method to return the property names
     * that you want to use as primary keys for this active record class.
     *
     * Note that an array should be returned even for a table with a single primary key.
     *
     * @throws Exception
     * @throws InvalidConfigException
     *
     * @return string[] The primary keys of the associated database table.
     */
    public function primaryKey(): array;

    /**
     * Returns the relation object with the specified name.
     *
     * @param string $name The relation name, for example, `orders` (case-sensitive).
     *
     * @return array|array[]|self|self[]|null The relation object.
     */
    public function relation(string $name): self|array|null;

    /**
     * Returns the relation query object with the specified name.
     *
     * A relation is defined by a getter method which returns an object implementing the {@see ActiveQueryInterface}
     * (normally this would be a relational {@see ActiveQuery} object).
     *
     * Relations can be defined using {@see hasOne()} and {@see hasMany()} methods. For example:
     *
     * ```php
     * public function relationQuery(string $name): ActiveQueryInterface
     * {
     *     return match ($name) {
     *         'orders' => $this->hasMany(Order::class, ['customer_id' => 'id']),
     *         'country' => $this->hasOne(Country::class, ['id' => 'country_id']),
     *         default => parent::relationQuery($name),
     *     };
     * }
     * ```
     *
     * @param string $name The relation name, for example, `orders` (case-sensitive).
     *
     * @throws InvalidArgumentException
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function relationQuery(string $name): ActiveQueryInterface;

    /**
     * Resets relation data for the specified name.
     *
     * @param string $name The relation name, for example, `orders` (case-sensitive).
     */
    public function resetRelation(string $name): void;

    /**
     * Saves the current record.
     *
     * This method will call {@see insert()} when {@see getIsNewRecord()|isNewRecord} is true, or {@see update()} when
     * {@see getIsNewRecord()|isNewRecord} is false.
     *
     * For example, to save a customer record:
     *
     * ```php
     * $customer = new Customer();
     * $customer->name = $name;
     * $customer->email = $email;
     * $customer->save();
     * ```
     *
     * @param array|null $propertyNames List of property names that need to be saved. Defaults to `null`,
     * meaning all changed property values will be saved.
     *
     * @return bool Whether the saving succeeded (that's no validation errors occurred).
     */
    public function save(array|null $propertyNames = null): bool;

    /**
     * Sets the named property value.
     *
     * @param string $propertyName The property name.
     *
     * @throws InvalidArgumentException If the named property doesn't exist.
     */
    public function set(string $propertyName, mixed $value): void;

    /**
     * Saves the changes to this active record into the associated database table.
     *
     * Only the {@see newValues() changed property values} will be saved into a database.
     *
     * For example, to update a customer record:
     *
     * ```php
     * $customer = new Customer();
     * $customer->name = $name;
     * $customer->email = $email;
     * $customer->update();
     * ```
     *
     * Note that it's possible the update doesn't affect any row in the table.
     * In this case, this method will return 0.
     * For this reason, you should use the following code to check if update() is successful or not:
     *
     * ```php
     * if ($customer->update() !== 0) {
     *     // update successful
     * } else {
     *     // update failed
     * }
     * ```
     *
     * @param array|null $propertyNames List of property names that need to be saved. Defaults to `null`, meaning all
     * changed property values will be saved.
     *
     * @throws StaleObjectException If {@see optimisticLock() optimistic locking} is enabled and the data being updated is
     * outdated.
     * @throws Throwable In case update failed.
     *
     * @return int The number of rows affected.
     */
    public function update(array|null $propertyNames = null): int;

    /**
     * Updates the whole table using the provided property values and conditions.
     *
     * For example, to change the status to be 1 for all customers whose status is 2:
     *
     * ```php
     * $customer = new Customer();
     * $customer->updateAll(['status' => 1], 'status = 2');
     * ```
     *
     * > Warning: If you don't specify any condition, this method will update **all** rows in the table.
     *
     * ```php
     * $customerQuery = new ActiveQuery(Customer::class);
     * $customers = $customerQuery->where('status = 2')->all();
     * foreach ($customers as $customer) {
     *     $customer->status = 1;
     *     $customer->update();
     * }
     * ```
     *
     * For a large set of models you might consider using {@see ActiveQuery::each()} to keep memory usage within limits.
     *
     * @param array $propertyValues Property values (name-value pairs) to be saved into the table.
     * @param array|string $condition The conditions that will be put in the `WHERE` part of the `UPDATE` SQL.
     * Please refer to {@see Query::where()} on how to specify this parameter.
     * @param array $params The parameters (name => value) to be bound to the query.
     *
     * @throws InvalidConfigException
     * @throws Throwable if the models can't be unlinked.
     * @throws Exception
     *
     * @return int The number of rows updated.
     */
    public function updateAll(array $propertyValues, array|string $condition = [], array $params = []): int;

    /**
     * Updates the specified properties.
     *
     * This method is a shortcut to {@see update()} when only a small set of properties need to be updated.
     *
     * You may specify the properties to be updated as name list or name-value pairs.
     * If the latter, the corresponding property values will be modified so.
     *
     * The method will then save the specified properties into a database.
     *
     * Note that this method will **not** perform data validation and will **not** trigger events.
     *
     * @param array $properties The properties (names or name-value pairs) to be updated.
     *
     * @throws Exception
     * @throws NotSupportedException
     *
     * @return int The number of rows affected.
     */
    public function updateProperties(array $properties): int;

    /**
     * Destroys the relationship between two records.
     *
     * The record with the foreign key of the relationship will be deleted if `$delete` is true.
     *
     * Otherwise, the foreign key will be set `null` and the record will be saved without validation.
     *
     * @param string $relationName The relation name, for example, `orders` (case-sensitive).
     * @param self $arClass The active record to be unlinked from the current one.
     * @param bool $delete Whether to delete the active record that contains the foreign key.
     * If false, the active record's foreign key will be set `null` and saved.
     * If true, the active record containing the foreign key will be deleted.
     */
    public function unlink(string $relationName, self $arClass, bool $delete = false): void;

    /**
     * Returns the old property values.
     *
     * @return array The old property values (name-value pairs).
     */
    public function oldValues(): array;

    /**
     * Populates an active record object using a row of data from the database/storage.
     *
     * This is an internal method meant to be called to create active record objects after fetching data from the
     * database.
     * It's mainly used by {@see ActiveQuery} to populate the query results into active records.
     *
     * @param array|object $row Property values (name => value).
     *
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function populateRecord(array|object $row): void;
}
