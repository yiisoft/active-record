<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

/**
 * ActiveRecordInterface.
 */
interface ActiveRecordInterface
{
    /**
     * Returns the primary key **name(s)** for this AR class.
     *
     * Note that an array should be returned even when the record only has a single primary key.
     *
     * For the primary key **value** see {@see getPrimaryKey()} instead.
     *
     * @return array the primary key name(s) for this AR class.
     */
    public function primaryKey(): array;

    /**
     * Returns the list of all attribute names of the record.
     *
     * @return array list of attribute names.
     */
    public function attributes(): array;

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
    public function getAttribute(string $name);

    /**
     * Sets the named attribute value.
     *
     * @param string $name the attribute name.
     * @param mixed $value the attribute value.
     *
     * {@see hasAttribute()}
     */
    public function setAttribute(string $name, $value): void;

    /**
     * Returns a value indicating whether the record has an attribute with the specified name.
     *
     * @param string $name the name of the attribute.
     *
     * @return bool whether the record has an attribute with the specified name.
     */
    public function hasAttribute(string $name): bool;

    /**
     * Returns the primary key value(s).
     *
     * @param bool $asArray whether to return the primary key value as an array. If true, the return value will be an
     * array with attribute names as keys and attribute values as values. Note that for composite primary keys, an array
     * will always be returned regardless of this parameter value.
     *
     * @return mixed the primary key value. An array (attribute name => attribute value) is returned if the primary key
     * is composite or `$asArray` is true. A string is returned otherwise (`null` will be returned if the key value is
     * `null`).
     */
    public function getPrimaryKey(bool $asArray = false);

    /**
     * Returns the old primary key value(s).
     *
     * This refers to the primary key value that is populated into the record after executing a find method (e.g.
     * findOne()).
     *
     * The value remains unchanged even if the primary key attribute is manually assigned with a different value.
     *
     * @param bool $asArray whether to return the primary key value as an array. If true, the return value will be an
     * array with column name as key and column value as value. If this is `false` (default), a scalar value will be
     * returned for non-composite primary key.
     *
     * @return mixed the old primary key value. An array (column name => column value) is returned if the primary key
     * is composite or `$asArray` is true. A string is returned otherwise (`null` will be returned if the key value is
     * `null`).
     */
    public function getOldPrimaryKey(bool $asArray = false);

    /**
     * Returns a value indicating whether the given set of attributes represents the primary key for this active record.
     *
     * @param array $keys the set of attributes to check.
     *
     * @return bool whether the given set of attributes represents the primary key for this active record.
     */
    public function isPrimaryKey(array $keys): bool;

    /**
     * Updates records using the provided attribute values and conditions.
     *
     * For example, to change the status to be 1 for all customers whose status is 2:
     *
     * ```php
     * $customer = new Customer($db);
     * $customer->updateAll(['status' => 1], ['status' => '2']);
     * ```
     *
     * @param array $attributes attribute values (name-value pairs) to be saved for the record. Unlike {@see update()}
     * these are not going to be validated.
     * @param array|string|null $condition the condition that matches the records that should get updated.
     * Please refer to {@see QueryInterface::where()} on how to specify this parameter. An empty condition will match
     * all records.
     * @param array $params
     *
     * @return int the number of rows updated.
     */
    public function updateAll(array $attributes, $condition = null, array $params = []): int;

    /**
     * Deletes records using the provided conditions.
     *
     * WARNING: If you do not specify any condition, this method will delete ALL rows in the table.
     *
     * For example, to delete all customers whose status is 3:
     *
     * ```php
     * $customer = new Customer($this->db);
     * $customer->deleteAll([status = 3]);
     * ```
     *
     * @param array|null $condition the condition that matches the records that should get deleted.
     * Please refer to {@see QueryInterface::where()} on how to specify this parameter.
     * An empty condition will match all records.
     *
     * @return int the number of rows deleted.
     */
    public function deleteAll(array $condition = null): int;

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
     * @param array|null $attributeNames list of attribute names that need to be saved. Defaults to `null`,
     * meaning all attributes that are loaded from DB will be saved.
     *
     * @return bool whether the saving succeeded (i.e. no validation errors occurred).
     */
    public function save(array $attributeNames = null): bool;

    /**
     * Inserts the record into the database using the attribute values of this record.
     *
     * Usage example:
     *
     * ```php
     * $customer = new Customer($db);
     * $customer->name = $name;
     * $customer->email = $email;
     * $customer->insert();
     * ```
     *
     * @param array|null $attributes list of attributes that need to be saved. Defaults to `null`, meaning all
     * attributes that are loaded from DB will be saved.
     *
     * @return bool whether the attributes are valid and the record is inserted successfully.
     */
    public function insert(?array $attributes = null): bool;

    /**
     * Saves the changes to this active record into the database.
     *
     * Usage example:
     *
     * ```php
     * $customerQuery = new ActiveQuery(Customer::class, $db);
     * $customer = $customerQuery->findOne($id);
     * $customer->name = $name;
     * $customer->email = $email;
     * $customer->update();
     * ```
     *
     * @param array|null $attributeNames list of attributes that need to be saved. Defaults to `null`, meaning all
     * attributes that are loaded from DB will be saved.
     *
     * @return bool|int the number of rows affected, or `false` if validation fails or updating process is stopped for
     * other reasons.
     *
     * Note that it is possible that the number of rows affected is 0, even though the update execution is successful.
     */
    public function update(array $attributeNames = null);

    /**
     * Deletes the record from the database.
     *
     * @return bool|int the number of rows deleted, or `false` if the deletion is unsuccessful for some reason.
     *
     * Note that it is possible that the number of rows deleted is 0, even though the deletion execution is successful.
     */
    public function delete();

    /**
     * Returns a value indicating whether the current record is new (not saved in the database).
     *
     * @return bool whether the record is new and should be inserted when calling {@see save()}.
     */
    public function getIsNewRecord(): bool;

    /**
     * Returns a value indicating whether the given active record is the same as the current one.
     *
     * Two {@see getIsNewRecord()|new} records are considered to be not equal.
     *
     * @param ActiveRecordInterface $record record to compare to.
     *
     * @return bool whether the two active records refer to the same row in the same database table.
     */
    public function equals(self $record): bool;

    /**
     * Returns the relation object with the specified name.
     *
     * A relation is defined by a getter method which returns an object implementing the {@see ActiveQueryInterface}
     * (normally this would be a relational {@see ActiveQuery} object).
     *
     * @param string $name the relation name, e.g. `orders` for a relation defined via `getOrders()` method
     * (case-sensitive).
     * @param bool $throwException whether to throw exception if the relation does not exist.
     *
     * @return ActiveQueryInterface|null the relational query object.
     */
    public function getRelation(string $name, bool $throwException = true): ?ActiveQueryInterface;

    /**
     * Populates the named relation with the related records.
     *
     * Note that this method does not check if the relation exists or not.
     *
     * @param string $name the relation name, e.g. `orders` for a relation defined via `getOrders()` method
     * (case-sensitive).
     * @param ActiveRecordInterface|array|null $records the related records to be populated into the relation.
     */
    public function populateRelation(string $name, $records): void;

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
     * @param string $name the case sensitive name of the relationship, e.g. `orders` for a relation defined via
     * `getOrders()` method.
     * @param ActiveRecordInterface $arClass the record to be linked with the current one.
     * @param array $extraColumns additional column values to be saved into the junction table. This parameter is only
     * meaningful for a relationship involving a junction table (i.e., a relation set with
     * {@see ActiveQueryInterface::via()}).
     */
    public function link(string $name, self $arClass, array $extraColumns = []): void;

    /**
     * Destroys the relationship between two records.
     *
     * The record with the foreign key of the relationship will be deleted if `$delete` is true.
     *
     * Otherwise, the foreign key will be set `null` and the record will be saved without validation.
     *
     * @param string $name the case sensitive name of the relationship, e.g. `orders` for a relation defined via
     * `getOrders()` method.
     * @param ActiveRecordInterface $arClass the active record to be unlinked from the current one.
     * @param bool $delete whether to delete the active record that contains the foreign key.
     * If false, the active record's foreign key will be set `null` and saved.
     * If true, the active record containing the foreign key will be deleted.
     */
    public function unlink(string $name, self $arClass, bool $delete = false): void;
}
