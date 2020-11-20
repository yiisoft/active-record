<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use function array_combine;
use function array_flip;
use function array_intersect;
use function array_key_exists;
use function array_keys;
use function array_search;
use function array_values;
use ArrayAccess;
use Closure;
use function count;
use function get_class;
use function in_array;
use function is_array;

use function is_int;
use IteratorAggregate;
use ReflectionException;
use function reset;
use Throwable;
use Yiisoft\ActiveRecord\Redis\ActiveQuery as RedisActiveQuery;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidCallException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Exception\StaleObjectException;

/**
 * ActiveRecord is the base class for classes representing relational data in terms of objects.
 *
 * See {@see ActiveRecord} for a concrete implementation.
 *
 * @property array $dirtyAttributes The changed attribute values (name-value pairs). This property is read-only.
 * @property bool $isNewRecord Whether the record is new and should be inserted when calling {@see save()}.
 * @property array $oldAttributes The old attribute values (name-value pairs). Note that the type of this property
 * differs in getter and setter. See {@see getOldAttributes()} and {@see setOldAttributes()} for details.
 * @property mixed $oldPrimaryKey The old primary key value. An array (column name => column value) is returned if the
 * primary key is composite. A string is returned otherwise (null will be returned if the key value is null).
 * This property is read-only.
 * @property mixed $primaryKey The primary key value. An array (column name => column value) is returned if the primary
 * key is composite. A string is returned otherwise (null will be returned if the key value is null).
 * This property is read-only.
 * @property array $relatedRecords An array of related records indexed by relation names. This property is read-only.
 */
abstract class BaseActiveRecord implements ActiveRecordInterface, IteratorAggregate, ArrayAccess
{
    use BaseActiveRecordTrait;

    private array $attributes = [];
    private ?array $oldAttributes = null;
    private array $related = [];
    private array $relationsDependencies = [];
    private ?ActiveRecordFactory $arFactory;
    protected ConnectionInterface $db;

    public function __construct(ConnectionInterface $db, ActiveRecordFactory $arFactory = null)
    {
        $this->arFactory = $arFactory;
        $this->db = $db;
    }

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
     * @param array $attributes attribute values (name-value pairs) to be saved into the table.
     * @param array|string|null $condition the conditions that will be put in the WHERE part of the UPDATE SQL.
     * Please refer to {@see Query::where()} on how to specify this parameter.
     * @param array $params
     *
     * @throws NotSupportedException if not overridden.
     *
     * @return int the number of rows updated.
     */
    public function updateAll(array $attributes, $condition = null, array $params = []): int
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported.');
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
     * @param array $counters the counters to be updated (attribute name => increment value). Use negative values if you
     * want to decrement the counters.
     * @param array|string $condition the conditions that will be put in the WHERE part of the UPDATE SQL.
     * Please refer to {@see Query::where()} on how to specify this parameter.
     *
     * @throws NotSupportedException if not override.
     *
     * @return int the number of rows updated.
     */
    public function updateAllCounters(array $counters, $condition = ''): int
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported.');
    }

    /**
     * Deletes rows in the table using the provided conditions.
     *
     * WARNING: If you do not specify any condition, this method will delete ALL rows in the table.
     *
     * For example, to delete all customers whose status is 3:
     *
     * ```php
     * $customer = new Customer($this->db);
     * $customer::deleteAll('status = 3');
     * ```
     *
     * @param array|null $condition the conditions that will be put in the WHERE part of the DELETE SQL.
     *
     * Please refer to {@see Query::where()} on how to specify this parameter.
     *
     * @throws NotSupportedException if not overridden.
     *
     * @return int the number of rows deleted.
     */
    public function deleteAll(array $condition = null): int
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported.');
    }

    /**
     * Returns the name of the column that stores the lock version for implementing optimistic locking.
     *
     * Optimistic locking allows multiple users to access the same record for edits and avoids potential conflicts. In
     * case when a user attempts to save the record upon some staled data (because another user has modified the data),
     * a {@see StaleObjectException} exception will be thrown, and the update or deletion is skipped.
     *
     * Optimistic locking is only supported by {@see update()} and {@see delete()}.
     *
     * To use Optimistic locking:
     *
     * 1. Create a column to store the version number of each row. The column type should be `BIGINT DEFAULT 0`.
     *    Override this method to return the name of this column.
     * 2. In the Web form that collects the user input, add a hidden field that stores the lock version of the recording
     *    being updated.
     * 3. In the controller action that does the data updating, try to catch the {@see StaleObjectException} and
     *    implement necessary business logic (e.g. merging the changes, prompting stated data) to resolve the conflict.
     *
     * @return string|null the column name that stores the lock version of a table row. If `null` is returned (default
     * implemented), optimistic locking will not be supported.
     */
    public function optimisticLock(): ?string
    {
        return null;
    }

    /**
     * Declares a `has-one` relation.
     *
     * The declaration is returned in terms of a relational {@see ActiveQuery} instance through which the related record
     * can be queried and retrieved back.
     *
     * A `has-one` relation means that there is at most one related record matching the criteria set by this relation,
     * e.g., a customer has one country.
     *
     * For example, to declare the `country` relation for `Customer` class, we can write the following code in the
     * `Customer` class:
     *
     * ```php
     * public function getCountry()
     * {
     *     return $this->hasOne(Country::className(), ['id' => 'country_id']);
     * }
     * ```
     *
     * Note that in the above, the 'id' key in the `$link` parameter refers to an attribute name in the related class
     * `Country`, while the 'country_id' value refers to an attribute name in the current AR class.
     *
     * Call methods declared in {@see ActiveQuery} to further customize the relation.
     *
     * @param array|string $class the class name of the related record.
     * @param array $link the primary-foreign key constraint. The keys of the array refer to the attributes of the
     * record associated with the `$class` model, while the values of the array refer to the corresponding attributes in
     * **this** AR class.
     *
     * @return ActiveQueryInterface the relational query object.
     */
    public function hasOne($class, array $link): ActiveQueryInterface
    {
        return $this->createRelationQuery($class, $link, false);
    }

    /**
     * Declares a `has-many` relation.
     *
     * The declaration is returned in terms of a relational {@see ActiveQuery} instance  through which the related
     * record can be queried and retrieved back.
     *
     * A `has-many` relation means that there are multiple related records matching the criteria set by this relation,
     * e.g., a customer has many orders.
     *
     * For example, to declare the `orders` relation for `Customer` class, we can write the following code in the
     * `Customer` class:
     *
     * ```php
     * public function getOrders()
     * {
     *     return $this->hasMany(Order::className(), ['customer_id' => 'id']);
     * }
     * ```
     *
     * Note that in the above, the 'customer_id' key in the `$link` parameter refers to an attribute name in the related
     * class `Order`, while the 'id' value refers to an attribute name in the current AR class.
     *
     * Call methods declared in {@see ActiveQuery} to further customize the relation.
     *
     * @param array|string $class the class name of the related record
     * @param array $link the primary-foreign key constraint. The keys of the array refer to the attributes of the
     * record associated with the `$class` model, while the values of the array refer to the corresponding attributes in
     * **this** AR class.
     *
     * @return ActiveQueryInterface the relational query object.
     */
    public function hasMany($class, array $link): ActiveQueryInterface
    {
        return $this->createRelationQuery($class, $link, true);
    }

    /**
     * Creates a query instance for `has-one` or `has-many` relation.
     *
     * @param string $arClass the class name of the related record.
     * @param array $link the primary-foreign key constraint.
     * @param bool $multiple whether this query represents a relation to more than one record.
     *
     * @return ActiveQueryInterface the relational query object.

     * {@see hasOne()}
     * {@see hasMany()}
     */
    protected function createRelationQuery(string $arClass, array $link, bool $multiple): ActiveQueryInterface
    {
        return $this->instantiateQuery($arClass)->primaryModel($this)->link($link)->multiple($multiple);
    }

    /**
     * Populates the named relation with the related records.
     *
     * Note that this method does not check if the relation exists or not.
     *
     * @param string $name the relation name, e.g. `orders` for a relation defined via `getOrders()` method
     * (case-sensitive).
     * @param ActiveRecordInterface|array|null $records the related records to be populated into the relation.
     */
    public function populateRelation(string $name, $records): void
    {
        foreach ($this->relationsDependencies as &$relationNames) {
            unset($relationNames[$name]);
        }

        $this->related[$name] = $records;
    }

    /**
     * Check whether the named relation has been populated with records.
     *
     * @param string $name the relation name, e.g. `orders` for a relation defined via `getOrders()` method
     * (case-sensitive).
     *
     * @return bool whether relation has been populated with records.
     *
     * {@see getRelation()}
     */
    public function isRelationPopulated(string $name): bool
    {
        return array_key_exists($name, $this->related);
    }

    /**
     * Returns all populated related records.
     *
     * @return array an array of related records indexed by relation names.
     *
     * {@see getRelation()}
     */
    public function getRelatedRecords(): array
    {
        return $this->related;
    }

    /**
     * Returns a value indicating whether the model has an attribute with the specified name.
     *
     * @param int|string $name the name or position of the attribute.
     *
     * @return bool whether the model has an attribute with the specified name.
     */
    public function hasAttribute($name): bool
    {
        return isset($this->attributes[$name]) || in_array($name, $this->attributes(), true);
    }

    /**
     * Returns the named attribute value.
     *
     * If this record is the result of a query and the attribute is not loaded, `null` will be returned.
     *
     * @param string $name the attribute name.
     *
     * @return mixed the attribute value. `null` if the attribute is not set or does not exist.
     *
     * {@see hasAttribute()}
     */
    public function getAttribute(string $name)
    {
        return $this->attributes[$name] ?? null;
    }

    /**
     * Sets the named attribute value.
     *
     * @param string $name the attribute name.
     * @param mixed $value the attribute value.
     *
     * @throws InvalidArgumentException if the named attribute does not exist.
     */
    public function setAttribute(string $name, $value): void
    {
        if ($this->hasAttribute($name)) {
            if (
                !empty($this->relationsDependencies[$name])
                && (!array_key_exists($name, $this->attributes) || $this->attributes[$name] !== $value)
            ) {
                $this->resetDependentRelations($name);
            }
            $this->attributes[$name] = $value;
        } else {
            throw new InvalidArgumentException(static::class . ' has no attribute named "' . $name . '".');
        }
    }

    /**
     * Returns the old attribute values.
     *
     * @return array the old attribute values (name-value pairs).
     */
    public function getOldAttributes(): array
    {
        return $this->oldAttributes ?? [];
    }

    /**
     * Sets the old attribute values.
     *
     * All existing old attribute values will be discarded.
     *
     * @param array|null $values old attribute values to be set. If set to `null` this record is considered to be
     * {@see isNewRecord|new}.
     */
    public function setOldAttributes(array $values = null): void
    {
        $this->oldAttributes = $values;
    }

    /**
     * Returns the old value of the named attribute.
     *
     * If this record is the result of a query and the attribute is not loaded, `null` will be returned.
     *
     * @param string $name the attribute name
     *
     * @return mixed the old attribute value. `null` if the attribute is not loaded before or does not exist.
     *
     * {@see hasAttribute()}
     */
    public function getOldAttribute(string $name)
    {
        return $this->oldAttributes[$name] ?? null;
    }

    /**
     * Sets the old value of the named attribute.
     *
     * @param string $name the attribute name.
     * @param mixed $value the old attribute value.
     *
     * @throws InvalidArgumentException if the named attribute does not exist.
     *
     * {@see hasAttribute()}
     */
    public function setOldAttribute(string $name, $value): void
    {
        if (isset($this->oldAttributes[$name]) || $this->hasAttribute($name)) {
            $this->oldAttributes[$name] = $value;
        } else {
            throw new InvalidArgumentException(static::class . ' has no attribute named "' . $name . '".');
        }
    }

    /**
     * Marks an attribute dirty.
     *
     * This method may be called to force updating a record when calling {@see update()}, even if there is no change
     * being made to the record.
     *
     * @param string $name the attribute name.
     */
    public function markAttributeDirty(string $name): void
    {
        unset($this->oldAttributes[$name]);
    }

    /**
     * Returns a value indicating whether the named attribute has been changed.
     *
     * @param string $name the name of the attribute.
     * @param bool $identical whether the comparison of new and old value is made for identical values using `===`,
     * defaults to `true`. Otherwise `==` is used for comparison.
     *
     * @return bool whether the attribute has been changed.
     */
    public function isAttributeChanged(string $name, bool $identical = true): bool
    {
        if (isset($this->attributes[$name], $this->oldAttributes[$name])) {
            if ($identical) {
                return $this->attributes[$name] !== $this->oldAttributes[$name];
            }

            return $this->attributes[$name] !== $this->oldAttributes[$name];
        }

        return isset($this->attributes[$name]) || isset($this->oldAttributes[$name]);
    }

    /**
     * Returns the attribute values that have been modified since they are loaded or saved most recently.
     *
     * The comparison of new and old values is made for identical values using `===`.
     *
     * @param array|null $names the names of the attributes whose values may be returned if they are changed recently.
     * If null, {@see attributes()} will be used.
     *
     * @return array the changed attribute values (name-value pairs).
     */
    public function getDirtyAttributes(array $names = null): array
    {
        if ($names === null) {
            $names = $this->attributes();
        }

        $names = array_flip($names);
        $attributes = [];

        if ($this->oldAttributes === null) {
            foreach ($this->attributes as $name => $value) {
                if (isset($names[$name])) {
                    $attributes[$name] = $value;
                }
            }
        } else {
            foreach ($this->attributes as $name => $value) {
                if (
                    isset($names[$name])
                    && (!array_key_exists($name, $this->oldAttributes) || $value !== $this->oldAttributes[$name])
                ) {
                    $attributes[$name] = $value;
                }
            }
        }

        return $attributes;
    }

    /**
     * Saves the current record.
     *
     * This method will call {@see insert()} when {@see isNewRecord} is `true`, or {@see update()} when
     * {@see isNewRecord} is `false`.
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
     * @param array|null $attributeNames list of attribute names that need to be saved. Defaults to null, meaning all
     * attributes that are loaded from DB will be saved.
     *
     * @throws Exception|StaleObjectException
     *
     * @return bool whether the saving succeeded (i.e. no validation errors occurred).
     */
    public function save(array $attributeNames = null): bool
    {
        if ($this->getIsNewRecord()) {
            return $this->insert($attributeNames);
        }

        return $this->update($attributeNames) !== false;
    }

    /**
     * Saves the changes to this active record into the associated database table.
     *
     * Only the {@see dirtyAttributes|changed attribute values} will be saved into database.
     *
     * For example, to update a customer record:
     *
     * ```php
     * $customerQuery = new ActiveQuery(Customer::class, $db);
     * $customer = $customerQuery->findOne(2);
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
     * @param array|null $attributeNames list of attribute names that need to be saved. Defaults to null, meaning all
     * attributes that are loaded from DB will be saved.
     *
     * @throws Exception|NotSupportedException in case update failed.
     * @throws StaleObjectException if {@see href='psi_element://optimisticLock'>|optimistic locking} is enabled and the
     * data being updated is outdated.
     *
     * @return false|int the number of rows affected, or `false` if validation fails or {@see beforeSave()} stops the
     * updating process.
     */
    public function update(array $attributeNames = null)
    {
        return $this->updateInternal($attributeNames);
    }

    /**
     * Updates the specified attributes.
     *
     * This method is a shortcut to {@see update()} when data validation is not needed and only a small set attributes
     * need to be updated.
     *
     * You may specify the attributes to be updated as name list or name-value pairs. If the latter, the corresponding
     * attribute values will be modified accordingly.
     *
     * The method will then save the specified attributes into database.
     *
     * Note that this method will **not** perform data validation and will **not** trigger events.
     *
     * @param array $attributes the attributes (names or name-value pairs) to be updated.
     *
     * @throws Exception|NotSupportedException
     *
     * @return int the number of rows affected.
     */
    public function updateAttributes(array $attributes): int
    {
        $attrs = [];

        foreach ($attributes as $name => $value) {
            if (is_int($name)) {
                $attrs[] = $value;
            } else {
                $this->$name = $value;
                $attrs[] = $name;
            }
        }

        $values = $this->getDirtyAttributes($attrs);

        if (empty($values) || $this->getIsNewRecord()) {
            return 0;
        }

        $rows = $this->updateAll($values, $this->getOldPrimaryKey(true));

        foreach ($values as $name => $value) {
            $this->oldAttributes[$name] = $this->attributes[$name];
        }

        return $rows;
    }

    /**
     * {@see update()}
     *
     * @param array|null $attributes attributes to update.
     *
     * @throws Exception|NotSupportedException|StaleObjectException
     *
     * @return int the number of rows affected.
     */
    protected function updateInternal(array $attributes = null): int
    {
        $values = $this->getDirtyAttributes($attributes);

        if (empty($values)) {
            return 0;
        }

        $condition = $this->getOldPrimaryKey(true);
        $lock = $this->optimisticLock();

        if ($lock !== null) {
            $values[$lock] = $this->$lock + 1;
            $condition[$lock] = $this->$lock;
        }

        /**
         * We do not check the return value of updateAll() because it's possible that the UPDATE statement doesn't
         * change anything and thus returns 0.
         */
        $rows = $this->updateAll($values, $condition);

        if ($lock !== null && !$rows) {
            throw new StaleObjectException('The object being updated is outdated.');
        }

        if (isset($values[$lock])) {
            $this->$lock = $values[$lock];
        }

        $changedAttributes = [];

        foreach ($values as $name => $value) {
            $changedAttributes[$name] = $this->oldAttributes[$name] ?? null;
            $this->oldAttributes[$name] = $value;
        }

        return $rows;
    }

    /**
     * Updates one or several counter columns for the current AR object.
     *
     * Note that this method differs from {@see updateAllCounters()} in that it only saves counters for the current AR
     * object.
     *
     * An example usage is as follows:
     *
     * ```php
     * $post = new Post($db);
     * $post->updateCounters(['view_count' => 1]);
     * ```
     *
     * @param array $counters the counters to be updated (attribute name => increment value), use negative values if you
     * want to decrement the counters.
     *
     * @throws Exception|NotSupportedException
     *
     * @return bool whether the saving is successful.
     *
     * {@see updateAllCounters()}
     */
    public function updateCounters(array $counters): bool
    {
        if ($this->updateAllCounters($counters, $this->getOldPrimaryKey(true)) > 0) {
            foreach ($counters as $name => $value) {
                if (!isset($this->attributes[$name])) {
                    $this->attributes[$name] = $value;
                } else {
                    $this->attributes[$name] += $value;
                }

                $this->oldAttributes[$name] = $this->attributes[$name];
            }

            return true;
        }

        return false;
    }

    /**
     * Deletes the table row corresponding to this active record.
     *
     * This method performs the following steps in order:
     *
     * @throws StaleObjectException if {@see optimisticLock|optimistic locking} is enabled and the data being deleted is
     * outdated.
     * @throws Exception in case delete failed.
     *
     * @return bool|int the number of rows deleted, or `false` if the deletion is unsuccessful for some reason.
     * Note that it is possible the number of rows deleted is 0, even though the deletion execution is successful.
     */
    public function delete()
    {
        /**
         * we do not check the return value of deleteAll() because it's possible the record is already deleted in
         * the database and thus the method will return 0
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

        $this->oldAttributes = null;

        return $result;
    }

    /**
     * Returns a value indicating whether the current record is new.
     *
     * @return bool whether the record is new and should be inserted when calling {@see save()}.
     */
    public function getIsNewRecord(): bool
    {
        return $this->oldAttributes === null;
    }

    /**
     * Sets the value indicating whether the record is new.
     *
     * @param bool $value whether the record is new and should be inserted when calling {@see save()}.
     *
     * @see getIsNewRecord()
     */
    public function setIsNewRecord(bool $value): void
    {
        $this->oldAttributes = $value ? null : $this->attributes;
    }

    /**
     * Repopulates this active record with the latest data.
     *
     * @return bool whether the row still exists in the database. If `true`, the latest data will be populated to this
     * active record. Otherwise, this record will remain unchanged.
     */
    public function refresh(): bool
    {
        /** @var $record BaseActiveRecord */
        $record = $this->instantiateQuery(static::class)->findOne($this->getPrimaryKey(true));

        return $this->refreshInternal($record);
    }

    /**
     * Repopulates this active record with the latest data from a newly fetched instance.
     *
     * @param BaseActiveRecord|null $record the record to take attributes from.
     *
     * @return bool whether refresh was successful.
     *
     * {@see refresh()}
     */
    protected function refreshInternal(self $record = null): bool
    {
        if ($record === null) {
            return false;
        }

        foreach ($this->attributes() as $name) {
            $this->attributes[$name] = $record->attributes[$name] ?? null;
        }

        $this->oldAttributes = $record->oldAttributes;
        $this->related = [];
        $this->relationsDependencies = [];

        return true;
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
        if ($this->getIsNewRecord() || $record->getIsNewRecord()) {
            return false;
        }

        return static::class === get_class($record) && $this->getPrimaryKey() === $record->getPrimaryKey();
    }

    /**
     * Returns the primary key value(s).
     *
     * @param bool $asArray whether to return the primary key value as an array. If `true`, the return value will be an
     * array with column names as keys and column values as values. Note that for composite primary keys, an array will
     * always be returned regardless of this parameter value.
     *
     * @property mixed The primary key value. An array (column name => column value) is returned if the primary key is
     * composite. A string is returned otherwise (null will be returned if the key value is null).
     *
     * @return mixed the primary key value. An array (column name => column value) is returned if the primary key is
     * composite or `$asArray` is `true`. A string is returned otherwise (null will be returned if the key value is
     * null).
     */
    public function getPrimaryKey(bool $asArray = false)
    {
        $keys = $this->primaryKey();

        if (!$asArray && count($keys) === 1) {
            return $this->attributes[$keys[0]] ?? null;
        }

        $values = [];

        foreach ($keys as $name) {
            $values[$name] = $this->attributes[$name] ?? null;
        }

        return $values;
    }

    /**
     * Returns the old primary key value(s).
     *
     * This refers to the primary key value that is populated into the record after executing a find method
     * (e.g. findOne()).
     *
     * The value remains unchanged even if the primary key attribute is manually assigned with a different value.
     *
     * @param bool $asArray whether to return the primary key value as an array. If `true`, the return value will be an
     * array with column name as key and column value as value. If this is `false` (default), a scalar value will be
     * returned for non-composite primary key.
     *
     * @property mixed The old primary key value. An array (column name => column value) is returned if the primary key
     * is composite. A string is returned otherwise (null will be returned if the key value is null).
     *
     * @throws Exception if the AR model does not have a primary key.
     *
     * @return mixed the old primary key value. An array (column name => column value) is returned if the primary key
     * is composite or `$asArray` is `true`. A string is returned otherwise (null will be returned if the key value is
     * null).
     */
    public function getOldPrimaryKey(bool $asArray = false)
    {
        $keys = $this->primaryKey();

        if (empty($keys)) {
            throw new Exception(
                static::class . ' does not have a primary key. You should either define a primary key for '
                . 'the corresponding table or override the primaryKey() method.'
            );
        }

        if (!$asArray && count($keys) === 1) {
            return $this->oldAttributes[$keys[0]] ?? null;
        }

        $values = [];

        foreach ($keys as $name) {
            $values[$name] = $this->oldAttributes[$name] ?? null;
        }

        return $values;
    }

    /**
     * Populates an active record object using a row of data from the database/storage.
     *
     * This is an internal method meant to be called to create active record objects after fetching data from the
     * database. It is mainly used by {@see ActiveQuery} to populate the query results into active records.
     *
     * @param array|object $row attribute values (name => value).
     */
    public function populateRecord($row): void
    {
        $columns = array_flip($this->attributes());

        foreach ($row as $name => $value) {
            if (isset($columns[$name])) {
                $this->attributes[$name] = $value;
            } elseif ($this->canSetProperty($name)) {
                $this->$name = $value;
            }
        }

        $this->oldAttributes = $this->attributes;
        $this->related = [];
        $this->relationsDependencies = [];
    }

    public function instantiateQuery(string $arClass): ActiveQueryInterface
    {
        if ($this->db->getDriverName() === 'redis') {
            return new RedisActiveQuery($arClass, $this->db, $this->arFactory);
        }

        return new ActiveQuery($arClass, $this->db, $this->arFactory);
    }

    /**
     * Establishes the relationship between two models.
     *
     * The relationship is established by setting the foreign key value(s) in one model to be the corresponding primary
     * key value(s) in the other model.
     *
     * The model with the foreign key will be saved into database without performing validation.
     *
     * If the relationship involves a junction table, a new row will be inserted into the junction table which contains
     * the primary key values from both models.
     *
     * Note that this method requires that the primary key value is not null.
     *
     * @param string $name the case sensitive name of the relationship, e.g. `orders` for a relation defined via
     * `getOrders()` method.
     * @param ActiveRecordInterface $arClass the model to be linked with the current one.
     * @param array $extraColumns additional column values to be saved into the junction table. This parameter is only
     * meaningful for a relationship involving a junction table (i.e., a relation set with
     * {@see ActiveRelationTrait::via()} or {@see ActiveQuery::viaTable()}).
     *
     * @throws Exception|InvalidArgumentException|InvalidCallException if the method is unable to link two models.
     * @throws InvalidConfigException|ReflectionException|Throwable
     */
    public function link(string $name, ActiveRecordInterface $arClass, array $extraColumns = []): void
    {
        $relation = $this->getRelation($name);

        if ($relation->getVia() !== null) {
            if ($this->getIsNewRecord() || $arClass->getIsNewRecord()) {
                throw new InvalidCallException(
                    'Unable to link models: the models being linked cannot be newly created.'
                );
            }

            if (is_array($relation->getVia())) {
                /** @var $viaRelation ActiveQuery */
                [$viaName, $viaRelation] = $relation->getVia();
                $viaClass = $viaRelation->getARInstance();
                /** unset $viaName so that it can be reloaded to reflect the change */
                unset($this->related[$viaName]);
            } else {
                $viaRelation = $relation->getVia();
                $from = $relation->getVia()->getFrom();
                $viaTable = reset($from);
            }

            $columns = [];

            foreach ($viaRelation->getLink() as $a => $b) {
                $columns[$a] = $this->$b;
            }

            foreach ($relation->getLink() as $a => $b) {
                $columns[$b] = $arClass->$a;
            }

            foreach ($extraColumns as $k => $v) {
                $columns[$k] = $v;
            }

            if (is_array($relation->getVia())) {
                foreach ($columns as $column => $value) {
                    $viaClass->$column = $value;
                }

                $viaClass->insert();
            } else {
                /** @var $viaTable string */
                $this->db->createCommand()->insert($viaTable, $columns)->execute();
            }
        } else {
            $p1 = $arClass->isPrimaryKey(array_keys($relation->getLink()));
            $p2 = $this->isPrimaryKey(array_values($relation->getLink()));

            if ($p1 && $p2) {
                if ($this->getIsNewRecord() && $arClass->getIsNewRecord()) {
                    throw new InvalidCallException('Unable to link models: at most one model can be newly created.');
                }

                if ($this->getIsNewRecord()) {
                    $this->bindModels(array_flip($relation->getLink()), $this, $arClass);
                } else {
                    $this->bindModels($relation->getLink(), $arClass, $this);
                }
            } elseif ($p1) {
                $this->bindModels(array_flip($relation->getLink()), $this, $arClass);
            } elseif ($p2) {
                $this->bindModels($relation->getLink(), $arClass, $this);
            } else {
                throw new InvalidCallException(
                    'Unable to link models: the link defining the relation does not involve any primary key.'
                );
            }
        }

        /** update lazily loaded related objects */
        if (!$relation->getMultiple()) {
            $this->related[$name] = $arClass;
        } elseif (isset($this->related[$name])) {
            if ($relation->getIndexBy() !== null) {
                if ($relation->getIndexBy() instanceof Closure) {
                    $index = $relation->indexBy($arClass);
                } else {
                    $index = $arClass->{$relation->getIndexBy()};
                }
                $this->related[$name][$index] = $arClass;
            } else {
                $this->related[$name][] = $arClass;
            }
        }
    }

    /**
     * Destroys the relationship between two models.
     *
     * The model with the foreign key of the relationship will be deleted if `$delete` is `true`. Otherwise, the
     * foreign key will be set `null` and the model will be saved without validation.
     *
     * @param string $name the case sensitive name of the relationship, e.g. `orders` for a relation defined via
     * `getOrders()` method.
     * @param ActiveRecordInterface $arClass the model to be unlinked from the current one. You have to make sure that
     * the active record is really related with the current model as this method does not check this.
     * @param bool $delete whether to delete the model that contains the foreign key. If `false`, the active records
     * foreign key will be set `null` and saved. If `true`, the model containing the foreign key will be deleted.
     *
     * @throws Exception|InvalidCallException|ReflectionException|StaleObjectException|Throwable if the models cannot be
     * unlinked.
     */
    public function unlink(string $name, ActiveRecordInterface $arClass, bool $delete = false): void
    {
        $relation = $this->getRelation($name);

        if ($relation->getVia() !== null) {
            if (is_array($relation->getVia())) {
                /** @var $viaRelation ActiveQuery */
                [$viaName, $viaRelation] = $relation->getVia();
                $viaClass = $viaRelation->getARInstance();
                unset($this->related[$viaName]);
            } else {
                $viaRelation = $relation->getVia();
                $from = $relation->getVia()->getFrom();
                $viaTable = reset($from);
            }

            $columns = [];
            foreach ($viaRelation->getLink() as $a => $b) {
                $columns[$a] = $this->$b;
            }

            foreach ($relation->getLink() as $a => $b) {
                $columns[$b] = $arClass->$a;
            }
            $nulls = [];

            foreach (array_keys($columns) as $a) {
                $nulls[$a] = null;
            }

            if (is_array($relation->getVia())) {
                /** @var $viaClass ActiveRecordInterface */
                if ($delete) {
                    $viaClass->deleteAll($columns);
                } else {
                    $viaClass->updateAll($nulls, $columns);
                }
            } else {
                /** @var $viaTable string */
                $command = $this->db->createCommand();
                if ($delete) {
                    $command->delete($viaTable, $columns)->execute();
                } else {
                    $command->update($viaTable, $nulls, $columns)->execute();
                }
            }
        } else {
            $p1 = $arClass->isPrimaryKey(array_keys($relation->getLink()));
            $p2 = $this->isPrimaryKey(array_values($relation->getLink()));
            if ($p2) {
                if ($delete) {
                    $arClass->delete();
                } else {
                    foreach ($relation->getLink() as $a => $b) {
                        $arClass->$a = null;
                    }
                    $arClass->save();
                }
            } elseif ($p1) {
                foreach ($relation->getLink() as $a => $b) {
                    /** relation via array valued attribute */
                    if (is_array($this->$b)) {
                        if (($key = array_search($arClass->$a, $this->$b, false)) !== false) {
                            $values = $this->$b;
                            unset($values[$key]);
                            $this->$b = array_values($values);
                        }
                    } else {
                        $this->$b = null;
                    }
                }
                $delete ? $this->delete() : $this->save();
            } else {
                throw new InvalidCallException('Unable to unlink models: the link does not involve any primary key.');
            }
        }

        if (!$relation->getMultiple()) {
            unset($this->related[$name]);
        } elseif (isset($this->related[$name])) {
            /** @var $b ActiveRecordInterface */
            foreach ($this->related[$name] as $a => $b) {
                if ($arClass->getPrimaryKey() === $b->getPrimaryKey()) {
                    unset($this->related[$name][$a]);
                }
            }
        }
    }

    /**
     * Destroys the relationship in current model.
     *
     * The model with the foreign key of the relationship will be deleted if `$delete` is `true`. Otherwise, the foreign
     * key will be set `null` and the model will be saved without validation.
     *
     * Note that to destroy the relationship without removing records make sure your keys can be set to null.
     *
     * @param string $name the case sensitive name of the relationship, e.g. `orders` for a relation defined via
     * `getOrders()` method.
     * @param bool $delete whether to delete the model that contains the foreign key.
     *
     * @throws Exception|ReflectionException|StaleObjectException|Throwable
     */
    public function unlinkAll(string $name, bool $delete = false): void
    {
        $relation = $this->getRelation($name);

        if ($relation->getVia() !== null) {
            if (is_array($relation->getVia())) {
                /* @var $viaRelation ActiveQuery */
                [$viaName, $viaRelation] = $relation->getVia();
                $viaClass = $viaRelation->getARInstance();
                unset($this->related[$viaName]);
            } else {
                $viaRelation = $relation->getVia();
                $from = $relation->getVia()->getFrom();
                $viaTable = reset($from);
            }

            $condition = [];
            $nulls = [];

            foreach ($viaRelation->getLink() as $a => $b) {
                $nulls[$a] = null;
                $condition[$a] = $this->$b;
            }

            if (!empty($viaRelation->getWhere())) {
                $condition = ['and', $condition, $viaRelation->getWhere()];
            }

            if (!empty($viaRelation->getOn())) {
                $condition = ['and', $condition, $viaRelation->getOn()];
            }

            if (is_array($relation->getVia())) {
                /** @var $viaClass ActiveRecordInterface */
                if ($delete) {
                    $viaClass->deleteAll($condition);
                } else {
                    $viaClass->updateAll($nulls, $condition);
                }
            } else {
                /** @var $viaTable string */
                $command = $this->db->createCommand();
                if ($delete) {
                    $command->delete($viaTable, $condition)->execute();
                } else {
                    $command->update($viaTable, $nulls, $condition)->execute();
                }
            }
        } else {
            $relatedModel = $relation->getARInstance();

            $link = $relation->getLink();
            if (!$delete && count($link) === 1 && is_array($this->{$b = reset($link)})) {
                /** relation via array valued attribute */
                $this->$b = [];
                $this->save();
            } else {
                $nulls = [];
                $condition = [];

                foreach ($relation->getLink() as $a => $b) {
                    $nulls[$a] = null;
                    $condition[$a] = $this->$b;
                }

                if (!empty($relation->getWhere())) {
                    $condition = ['and', $condition, $relation->getWhere()];
                }

                if (!empty($relation->getOn())) {
                    $condition = ['and', $condition, $relation->getOn()];
                }

                if ($delete) {
                    $relatedModel->deleteAll($condition);
                } else {
                    $relatedModel->updateAll($nulls, $condition);
                }
            }
        }

        unset($this->related[$name]);
    }

    private function bindModels(
        array $link,
        ActiveRecordInterface $foreignModel,
        ActiveRecordInterface $primaryModel
    ): void {
        foreach ($link as $fk => $pk) {
            $value = $primaryModel->$pk;

            if ($value === null) {
                throw new InvalidCallException(
                    'Unable to link active record: the primary key of ' . get_class($primaryModel) . ' is null.'
                );
            }

            /** relation via array valued attribute */
            if (is_array($foreignModel->$fk)) {
                $foreignModel->{$fk}[] = $value;
            } else {
                $foreignModel->{$fk} = $value;
            }
        }

        $foreignModel->save();
    }

    /**
     * Returns a value indicating whether the given set of attributes represents the primary key for this model.
     *
     * @param array $keys the set of attributes to check.
     *
     * @return bool whether the given set of attributes represents the primary key for this model.
     */
    public function isPrimaryKey(array $keys): bool
    {
        $pks = $this->primaryKey();

        if (count($keys) === count($pks)) {
            return count(array_intersect($keys, $pks)) === count($pks);
        }

        return false;
    }

    public function fields(): array
    {
        $fields = array_keys($this->attributes);

        return array_combine($fields, $fields);
    }

    public function extraFields(): array
    {
        $fields = array_keys($this->getRelatedRecords());

        return array_combine($fields, $fields);
    }

    /**
     * Resets dependent related models checking if their links contain specific attribute.
     *
     * @param string $attribute The changed attribute name.
     */
    private function resetDependentRelations(string $attribute): void
    {
        foreach ($this->relationsDependencies[$attribute] as $relation) {
            unset($this->related[$relation]);
        }

        unset($this->relationsDependencies[$attribute]);
    }

    /**
     * Sets relation dependencies for a property.
     *
     * @param string $name property name.
     * @param ActiveQuery $relation relation instance.
     * @param string|null $viaRelationName intermediate relation.
     */
    private function setRelationDependencies(
        string $name,
        ActiveQuery $relation,
        ?string $viaRelationName = null
    ): void {
        if (empty($relation->getVia()) && $relation->getLink()) {
            foreach ($relation->getLink() as $attribute) {
                $this->relationsDependencies[$attribute][$name] = $name;
                if ($viaRelationName !== null) {
                    $this->relationsDependencies[$attribute][] = $viaRelationName;
                }
            }
        } elseif ($relation->getVia() instanceof ActiveQueryInterface) {
            $this->setRelationDependencies($name, $relation->getVia());
        } elseif (is_array($relation->getVia())) {
            [$viaRelationName, $viaQuery] = $relation->getVia();
            $this->setRelationDependencies($name, $viaQuery, $viaRelationName);
        }
    }

    /**
     * Returns attribute values.
     *
     * @param array|null $names list of attributes whose value needs to be returned. Defaults to null, meaning all
     * attributes listed in {@see attributes()} will be returned. If it is an array, only the attributes in the array
     * will be returned.
     * @param array $except list of attributes whose value should NOT be returned.
     *
     * @return array attribute values (name => value).
     */
    public function getAttributes(array $names = null, array $except = []): array
    {
        $values = [];

        if ($names === null) {
            $names = $this->attributes();
        }

        foreach ($names as $name) {
            $values[$name] = $this->$name;
        }

        foreach ($except as $name) {
            unset($values[$name]);
        }

        return $values;
    }

    /**
     * Sets the attribute values in a massive way.
     *
     * @param array $values attribute values (name => value) to be assigned to the model.
     *
     * {@see attributes()}
     */
    public function setAttributes(array $values): void
    {
        foreach ($values as $name => $value) {
            if (in_array($name, $this->attributes(), true)) {
                $this->$name = $value;
            }
        }
    }
}
