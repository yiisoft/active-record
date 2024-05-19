<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Closure;
use ReflectionException;
use Throwable;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidCallException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Exception\StaleObjectException;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Helper\DbStringHelper;

use function array_diff_key;
use function array_diff;
use function array_fill_keys;
use function array_flip;
use function array_intersect;
use function array_intersect_key;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_search;
use function array_values;
use function count;
use function get_object_vars;
use function in_array;
use function is_array;
use function is_int;
use function reset;

/**
 * ActiveRecord is the base class for classes representing relational data in terms of objects.
 *
 * See {@see ActiveRecord} for a concrete implementation.
 */
abstract class BaseActiveRecord implements ActiveRecordInterface
{
    use BaseActiveRecordTrait;

    private array $attributes = [];
    private array|null $oldAttributes = null;
    private array $related = [];
    /** @psalm-var string[][] */
    private array $relationsDependencies = [];

    public function __construct(
        protected ConnectionInterface $db,
        private ActiveRecordFactory|null $arFactory = null,
        private string $tableName = ''
    ) {
    }

    public function delete(): int
    {
        return $this->deleteInternal();
    }

    public function deleteAll(array $condition = [], array $params = []): int
    {
        $command = $this->db->createCommand();
        $command->delete($this->getTableName(), $condition, $params);

        return $command->execute();
    }

    public function equals(ActiveRecordInterface $record): bool
    {
        if ($this->getIsNewRecord() || $record->getIsNewRecord()) {
            return false;
        }

        return $this->getTableName() === $record->getTableName() && $this->getPrimaryKey() === $record->getPrimaryKey();
    }

    public function getAttribute(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function getAttributes(array $names = null, array $except = []): array
    {
        $names ??= $this->attributes();
        $attributes = array_merge($this->attributes, get_object_vars($this));

        if ($except !== []) {
            $names = array_diff($names, $except);
        }

        return array_intersect_key($attributes, array_flip($names));
    }

    public function getIsNewRecord(): bool
    {
        return $this->oldAttributes === null;
    }

    /**
     * Returns the old value of the named attribute.
     *
     * If this record is the result of a query and the attribute is not loaded, `null` will be returned.
     *
     * @param string $name The attribute name.
     *
     * @return mixed the old attribute value. `null` if the attribute is not loaded before or does not exist.
     *
     * {@see hasAttribute()}
     */
    public function getOldAttribute(string $name): mixed
    {
        return $this->oldAttributes[$name] ?? null;
    }

    /**
     * Returns the attribute values that have been modified since they are loaded or saved most recently.
     *
     * The comparison of new and old values is made for identical values using `===`.
     *
     * @param array|null $names The names of the attributes whose values may be returned if they are changed recently.
     * If null, {@see attributes()} will be used.
     *
     * @return array The changed attribute values (name-value pairs).
     */
    public function getDirtyAttributes(array $names = null): array
    {
        $attributes = $this->getAttributes($names);

        if ($this->oldAttributes === null) {
            return $attributes;
        }

        $result = array_diff_key($attributes, $this->oldAttributes);

        foreach (array_diff_key($attributes, $result) as $name => $value) {
            if ($value !== $this->oldAttributes[$name]) {
                $result[$name] = $value;
            }
        }

        return $result;
    }

    public function getOldAttributes(): array
    {
        return $this->oldAttributes ?? [];
    }

    /**
     * @throws InvalidConfigException
     * @throws Exception
     */
    public function getOldPrimaryKey(bool $asArray = false): mixed
    {
        $keys = $this->primaryKey();

        if (empty($keys)) {
            throw new Exception(
                static::class . ' does not have a primary key. You should either define a primary key for '
                . 'the corresponding table or override the primaryKey() method.'
            );
        }

        if (count($keys) === 1) {
            $key = $this->oldAttributes[$keys[0]] ?? null;

            return $asArray ? [$keys[0] => $key] : $key;
        }

        $values = [];

        foreach ($keys as $name) {
            $values[$name] = $this->oldAttributes[$name] ?? null;
        }

        return $values;
    }

    public function getPrimaryKey(bool $asArray = false): mixed
    {
        $keys = $this->primaryKey();

        if (count($keys) === 1) {
            return $asArray ? [$keys[0] => $this->getAttribute($keys[0])] : $this->getAttribute($keys[0]);
        }

        $values = [];

        foreach ($keys as $name) {
            $values[$name] = $this->getAttribute($name);
        }

        return $values;
    }

    /**
     * Returns all populated related records.
     *
     * @return array An array of related records indexed by relation names.
     *
     * {@see getRelation()}
     */
    public function getRelatedRecords(): array
    {
        return $this->related;
    }

    public function hasAttribute($name): bool
    {
        return isset($this->attributes[$name]) || in_array($name, $this->attributes(), true);
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
     * @param string $class The class name of the related record
     * @param array $link The primary-foreign key constraint. The keys of the array refer to the attributes of the
     * record associated with the `$class` model, while the values of the array refer to the corresponding attributes in
     * **this** AR class.
     *
     * @return ActiveQueryInterface The relational query object.
     *
     * @psalm-param class-string<ActiveRecordInterface> $class
     */
    public function hasMany(string $class, array $link): ActiveQueryInterface
    {
        return $this->createRelationQuery($class, $link, true);
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
     * @param string $class The class name of the related record.
     * @param array $link The primary-foreign key constraint. The keys of the array refer to the attributes of the
     * record associated with the `$class` model, while the values of the array refer to the corresponding attributes in
     * **this** AR class.
     *
     * @return ActiveQueryInterface The relational query object.
     *
     * @psalm-param class-string<ActiveRecordInterface> $class
     */
    public function hasOne(string $class, array $link): ActiveQueryInterface
    {
        return $this->createRelationQuery($class, $link, false);
    }

    /**
     * @psalm-param class-string<ActiveRecordInterface> $arClass
     */
    public function instantiateQuery(string $arClass): ActiveQueryInterface
    {
        return new ActiveQuery($arClass, $this->db, $this->arFactory);
    }

    /**
     * Returns a value indicating whether the named attribute has been changed.
     *
     * @param string $name The name of the attribute.
     * @param bool $identical Whether the comparison of new and old value is made for identical values using `===`,
     * defaults to `true`. Otherwise `==` is used for comparison.
     *
     * @return bool Whether the attribute has been changed.
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

    public function isPrimaryKey(array $keys): bool
    {
        $pks = $this->primaryKey();

        return count($keys) === count($pks)
            && count(array_intersect($keys, $pks)) === count($pks);
    }

    public function isRelationPopulated(string $name): bool
    {
        return array_key_exists($name, $this->related);
    }

    public function link(string $name, ActiveRecordInterface $arClass, array $extraColumns = []): void
    {
        $viaClass = null;
        $viaTable = null;
        $relation = $this->getRelation($name);
        $via = $relation?->getVia();

        if ($via !== null) {
            if ($this->getIsNewRecord() || $arClass->getIsNewRecord()) {
                throw new InvalidCallException(
                    'Unable to link models: the models being linked cannot be newly created.'
                );
            }

            if (is_array($via)) {
                [$viaName, $viaRelation] = $via;
                /** @psalm-var ActiveQueryInterface $viaRelation */
                $viaClass = $viaRelation->getARInstance();
                // unset $viaName so that it can be reloaded to reflect the change.
                /** @psalm-var string $viaName */
                unset($this->related[$viaName]);
            }

            if ($via instanceof ActiveQueryInterface) {
                $viaRelation = $via;
                $from = $via->getFrom();
                /** @psalm-var string $viaTable */
                $viaTable = reset($from);
            }

            $columns = [];

            /** @psalm-var ActiveQueryInterface|null $viaRelation */
            $viaLink = $viaRelation?->getLink() ?? [];

            /**
             * @psalm-var string $a
             * @psalm-var string $b
             */
            foreach ($viaLink as $a => $b) {
                /** @psalm-var mixed */
                $columns[$a] = $this->$b;
            }

            $link = $relation?->getLink() ?? [];

            /**
             * @psalm-var string $a
             * @psalm-var string $b
             */
            foreach ($link as $a => $b) {
                /** @psalm-var mixed */
                $columns[$b] = $arClass->$a;
            }

            /**
             * @psalm-var string $k
             * @psalm-var mixed $v
             */
            foreach ($extraColumns as $k => $v) {
                /** @psalm-var mixed */
                $columns[$k] = $v;
            }

            if ($viaClass instanceof ActiveRecordInterface && is_array($via)) {
                /**
                 * @psalm-var string $column
                 * @psalm-var mixed $value
                 */
                foreach ($columns as $column => $value) {
                    $viaClass->$column = $value;
                }

                $viaClass->insert();
            } elseif (is_string($viaTable)) {
                $this->db->createCommand()->insert($viaTable, $columns)->execute();
            }
        } elseif ($relation instanceof ActiveQueryInterface) {
            $link = $relation->getLink();
            $p1 = $arClass->isPrimaryKey(array_keys($link));
            $p2 = $this->isPrimaryKey(array_values($link));

            if ($p1 && $p2) {
                if ($this->getIsNewRecord() && $arClass->getIsNewRecord()) {
                    throw new InvalidCallException('Unable to link models: at most one model can be newly created.');
                }

                if ($this->getIsNewRecord()) {
                    $this->bindModels(array_flip($link), $this, $arClass);
                } else {
                    $this->bindModels($link, $arClass, $this);
                }
            } elseif ($p1) {
                $this->bindModels(array_flip($link), $this, $arClass);
            } elseif ($p2) {
                $this->bindModels($link, $arClass, $this);
            } else {
                throw new InvalidCallException(
                    'Unable to link models: the link defining the relation does not involve any primary key.'
                );
            }
        }

        // update lazily loaded related objects
        if ($relation instanceof ActiveRecordInterface && !$relation->getMultiple()) {
            $this->related[$name] = $arClass;
        } elseif (isset($this->related[$name])) {
            $indexBy = $relation?->getIndexBy();
            if ($indexBy !== null) {
                if ($indexBy instanceof Closure) {
                    $index = $relation?->indexBy($arClass::class);
                } else {
                    $index = $arClass->$indexBy;
                }

                if ($index !== null) {
                    $this->related[$name][$index] = $arClass;
                }
            } else {
                $this->related[$name][] = $arClass;
            }
        }
    }

    /**
     * Marks an attribute dirty.
     *
     * This method may be called to force updating a record when calling {@see update()}, even if there is no change
     * being made to the record.
     *
     * @param string $name The attribute name.
     */
    public function markAttributeDirty(string $name): void
    {
        if ($this->oldAttributes !== null && $name !== '') {
            unset($this->oldAttributes[$name]);
        }
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
     * @return string|null The column name that stores the lock version of a table row. If `null` is returned (default
     * implemented), optimistic locking will not be supported.
     */
    public function optimisticLock(): string|null
    {
        return null;
    }

    /**
     * Populates an active record object using a row of data from the database/storage.
     *
     * This is an internal method meant to be called to create active record objects after fetching data from the
     * database. It is mainly used by {@see ActiveQuery} to populate the query results into active records.
     *
     * @param array|object $row Attribute values (name => value).
     */
    public function populateRecord(array|object $row): void
    {
        foreach ($row as $name => $value) {
            if (property_exists($this, $name)) {
                $this->$name = $value;
            } else {
                $this->attributes[$name] = $value;
            }

            $this->oldAttributes[$name] = $value;
        }

        $this->related = [];
        $this->relationsDependencies = [];
    }

    public function populateRelation(string $name, array|ActiveRecordInterface|null $records): void
    {
        foreach ($this->relationsDependencies as &$relationNames) {
            unset($relationNames[$name]);
        }

        $this->related[$name] = $records;
    }

    /**
     * Repopulates this active record with the latest data.
     *
     * @return bool Whether the row still exists in the database. If `true`, the latest data will be populated to this
     * active record. Otherwise, this record will remain unchanged.
     */
    public function refresh(): bool
    {
        $record = $this->instantiateQuery(static::class)->findOne($this->getPrimaryKey(true));

        return $this->refreshInternal($record);
    }

    /**
     * Saves the current record.
     *
     * This method will call {@see insert()} when {@see getIsNewRecord} is `true`, or {@see update()} when
     * {@see getIsNewRecord} is `false`.
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
     * @param array|null $attributeNames List of attribute names that need to be saved. Defaults to null, meaning all
     * attributes that are loaded from DB will be saved.
     *
     * @throws InvalidConfigException
     * @throws StaleObjectException
     * @throws Throwable
     *
     * @return bool Whether the saving succeeded (i.e. no validation errors occurred).
     */
    public function save(array $attributeNames = null): bool
    {
        if ($this->getIsNewRecord()) {
            return $this->insert($attributeNames);
        }

        $this->update($attributeNames);

        return true;
    }

    public function setAttribute(string $name, mixed $value): void
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
     * Sets the attribute values in a massive way.
     *
     * @param array $values Attribute values (name => value) to be assigned to the model.
     *
     * {@see attributes()}
     */
    public function setAttributes(array $values): void
    {
        $values = array_intersect_key($values, array_flip($this->attributes()));

        /** @psalm-var mixed $value */
        foreach ($values as $name => $value) {
            $this->$name = $value;
        }
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
     * Sets the old value of the named attribute.
     *
     * @param string $name The attribute name.
     *
     * @throws InvalidArgumentException If the named attribute does not exist.
     *
     * {@see hasAttribute()}
     */
    public function setOldAttribute(string $name, mixed $value): void
    {
        if (isset($this->oldAttributes[$name]) || $this->hasAttribute($name)) {
            $this->oldAttributes[$name] = $value;
        } else {
            throw new InvalidArgumentException(static::class . ' has no attribute named "' . $name . '".');
        }
    }

    /**
     * Sets the old attribute values.
     *
     * All existing old attribute values will be discarded.
     *
     * @param array|null $values Old attribute values to be set. If set to `null` this record is considered to be
     * {@see isNewRecord|new}.
     */
    public function setOldAttributes(array $values = null): void
    {
        $this->oldAttributes = $values;
    }

    public function update(array $attributeNames = null): int
    {
        return $this->updateInternal($attributeNames);
    }

    public function updateAll(array $attributes, array|string $condition = [], array $params = []): int
    {
        $command = $this->db->createCommand();

        $command->update($this->getTableName(), $attributes, $condition, $params);

        return $command->execute();
    }

    public function updateAttributes(array $attributes): int
    {
        $attrs = [];

        foreach ($attributes as $name => $value) {
            if (is_int($name)) {
                $attrs[] = $value;
            } else {
                $this->setAttribute($name, $value);
                $attrs[] = $name;
            }
        }

        $values = $this->getDirtyAttributes($attrs);

        if (empty($values) || $this->getIsNewRecord()) {
            return 0;
        }

        $rows = $this->updateAll($values, $this->getOldPrimaryKey(true));

        $this->oldAttributes = array_merge($this->oldAttributes ?? [], $values);

        return $rows;
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
    public function updateAllCounters(array $counters, array|string $condition = '', array $params = []): int
    {
        $n = 0;

        /** @psalm-var array<string, int> $counters */
        foreach ($counters as $name => $value) {
            $counters[$name] = new Expression("[[$name]]+:bp$n", [":bp$n" => $value]);
            $n++;
        }

        $command = $this->db->createCommand();
        $command->update($this->getTableName(), $counters, $condition, $params);

        return $command->execute();
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
     * @param array $counters The counters to be updated (attribute name => increment value), use negative values if you
     * want to decrement the counters.
     *
     * @psalm-param array<string, int> $counters
     *
     * @throws Exception
     * @throws NotSupportedException
     *
     * @return bool Whether the saving is successful.
     *
     * {@see updateAllCounters()}
     */
    public function updateCounters(array $counters): bool
    {
        if ($this->updateAllCounters($counters, $this->getOldPrimaryKey(true)) === 0) {
            return false;
        }

        foreach ($counters as $name => $value) {
            $value += $this->$name ?? 0;
            $this->$name = $value;
            $this->oldAttributes[$name] = $value;
        }

        return true;
    }

    public function unlink(string $name, ActiveRecordInterface $arClass, bool $delete = false): void
    {
        $viaClass = null;
        $viaTable = null;
        $relation = $this->getRelation($name);
        $viaRelation = $relation?->getVia();

        if ($viaRelation !== null) {
            if (is_array($viaRelation)) {
                [$viaName, $viaRelation] = $viaRelation;
                /** @psalm-var ActiveQueryInterface $viaRelation */
                $viaClass = $viaRelation->getARInstance();
                /** @psalm-var string $viaName */
                unset($this->related[$viaName]);
            }

            $columns = [];
            $nulls = [];

            if ($viaRelation instanceof ActiveQueryInterface) {
                $from = $viaRelation->getFrom();
                /** @psalm-var mixed $viaTable */
                $viaTable = reset($from);

                foreach ($viaRelation->getLink() as $a => $b) {
                    /** @psalm-var mixed */
                    $columns[$a] = $this->$b;
                }

                $link = $relation?->getLink() ?? [];

                foreach ($link as $a => $b) {
                    /** @psalm-var mixed */
                    $columns[$b] = $arClass->$a;
                }

                $nulls = array_fill_keys(array_keys($columns), null);

                if ($viaRelation->getOn() !== null) {
                    $columns = ['and', $columns, $viaRelation->getOn()];
                }
            }

            if ($viaClass instanceof ActiveRecordInterface) {
                if ($delete) {
                    $viaClass->deleteAll($columns);
                } else {
                    $viaClass->updateAll($nulls, $columns);
                }
            } elseif (is_string($viaTable)) {
                $command = $this->db->createCommand();
                if ($delete) {
                    $command->delete($viaTable, $columns)->execute();
                } else {
                    $command->update($viaTable, $nulls, $columns)->execute();
                }
            }
        } elseif ($relation instanceof ActiveQueryInterface) {
            if ($this->isPrimaryKey($relation->getLink())) {
                if ($delete) {
                    $arClass->delete();
                } else {
                    foreach ($relation->getLink() as $a => $b) {
                        $arClass->$a = null;
                    }
                    $arClass->save();
                }
            } elseif ($arClass->isPrimaryKey(array_keys($relation->getLink()))) {
                foreach ($relation->getLink() as $a => $b) {
                    /** @psalm-var mixed $values */
                    $values = $this->$b;
                    /** relation via array valued attribute */
                    if (is_array($values)) {
                        if (($key = array_search($arClass->$a, $values, false)) !== false) {
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

        if ($relation instanceof ActiveQueryInterface && !$relation->getMultiple()) {
            unset($this->related[$name]);
        } elseif (isset($this->related[$name]) && is_array($this->related[$name])) {
            /** @psalm-var array<array-key, ActiveRecordInterface> $related */
            $related = $this->related[$name];
            foreach ($related as $a => $b) {
                if ($arClass->getPrimaryKey() === $b->getPrimaryKey()) {
                    unset($this->related[$name][$a]);
                }
            }
        }
    }

    /**
     * Destroys the relationship in current model.
     *
     * The active record with the foreign key of the relationship will be deleted if `$delete` is `true`. Otherwise, the
     * foreign key will be set `null` and the model will be saved without validation.
     *
     * Note that to destroy the relationship without removing records make sure your keys can be set to null.
     *
     * @param string $name The case sensitive name of the relationship, e.g. `orders` for a relation defined via
     * `getOrders()` method.
     * @param bool $delete Whether to delete the model that contains the foreign key.
     *
     * @throws Exception
     * @throws ReflectionException
     * @throws StaleObjectException
     * @throws Throwable
     */
    public function unlinkAll(string $name, bool $delete = false): void
    {
        $viaClass = null;
        $viaTable = null;
        $relation = $this->getRelation($name);
        $viaRelation = $relation?->getVia();

        if ($relation instanceof ActiveQueryInterface && $viaRelation !== null) {
            if (is_array($viaRelation)) {
                [$viaName, $viaRelation] = $viaRelation;
                /** @psalm-var ActiveQueryInterface $viaRelation */
                $viaClass = $viaRelation->getARInstance();
                /** @psalm-var string $viaName */
                unset($this->related[$viaName]);
            } else {
                $from = $viaRelation->getFrom();
                /** @psalm-var mixed $viaTable */
                $viaTable = reset($from);
            }

            $condition = [];
            $nulls = [];

            if ($viaRelation instanceof ActiveQueryInterface) {
                foreach ($viaRelation->getLink() as $a => $b) {
                    $nulls[$a] = null;
                    /** @psalm-var mixed */
                    $condition[$a] = $this->$b;
                }

                if (!empty($viaRelation->getWhere())) {
                    $condition = ['and', $condition, $viaRelation->getWhere()];
                }

                if (!empty($viaRelation->getOn())) {
                    $condition = ['and', $condition, $viaRelation->getOn()];
                }
            }

            if ($viaClass instanceof ActiveRecordInterface) {
                if ($delete) {
                    $viaClass->deleteAll($condition);
                } else {
                    $viaClass->updateAll($nulls, $condition);
                }
            } elseif (is_string($viaTable)) {
                $command = $this->db->createCommand();
                if ($delete) {
                    $command->delete($viaTable, $condition)->execute();
                } else {
                    $command->update($viaTable, $nulls, $condition)->execute();
                }
            }
        } elseif ($relation instanceof ActiveQueryInterface) {
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
                    /** @psalm-var mixed */
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

    /**
     * Sets relation dependencies for a property.
     *
     * @param string $name property name.
     * @param ActiveQueryInterface $relation relation instance.
     * @param string|null $viaRelationName intermediate relation.
     */
    private function setRelationDependencies(
        string $name,
        ActiveQueryInterface $relation,
        string $viaRelationName = null
    ): void {
        $via = $relation->getVia();

        if (empty($via)) {
            foreach ($relation->getLink() as $attribute) {
                $this->relationsDependencies[$attribute][$name] = $name;
                if ($viaRelationName !== null) {
                    $this->relationsDependencies[$attribute][] = $viaRelationName;
                }
            }
        } elseif ($via instanceof ActiveQueryInterface) {
            $this->setRelationDependencies($name, $via);
        } else {
            /**
             * @psalm-var string|null $viaRelationName
             * @psalm-var ActiveQueryInterface $viaQuery
             */
            [$viaRelationName, $viaQuery] = $via;
            $this->setRelationDependencies($name, $viaQuery, $viaRelationName);
        }
    }

    /**
     * Creates a query instance for `has-one` or `has-many` relation.
     *
     * @param string $arClass The class name of the related record.
     * @param array $link The primary-foreign key constraint.
     * @param bool $multiple Whether this query represents a relation to more than one record.
     *
     * @return ActiveQueryInterface The relational query object.
     *
     * @psalm-param class-string<ActiveRecordInterface> $arClass

     * {@see hasOne()}
     * {@see hasMany()}
     */
    protected function createRelationQuery(string $arClass, array $link, bool $multiple): ActiveQueryInterface
    {
        return $this->instantiateQuery($arClass)->primaryModel($this)->link($link)->multiple($multiple);
    }

    /**
     * {@see delete()}
     *
     * @throws Exception
     * @throws StaleObjectException
     * @throws Throwable
     *
     * @return int The number of rows deleted.
     */
    protected function deleteInternal(): int
    {
        /**
         * We do not check the return value of deleteAll() because it's possible the record is already deleted in
         * the database and thus the method will return 0
         */
        $condition = $this->getOldPrimaryKey(true);
        $lock = $this->optimisticLock();

        if ($lock !== null) {
            $condition[$lock] = $this->$lock;

            $result = $this->deleteAll($condition);

            if ($result === 0) {
                throw new StaleObjectException('The object being deleted is outdated.');
            }
        } else {
            $result = $this->deleteAll($condition);
        }

        $this->setOldAttributes();

        return $result;
    }

    /**
     * Repopulates this active record with the latest data from a newly fetched instance.
     *
     * @param ActiveRecordInterface|array|null $record The record to take attributes from.
     *
     * @return bool Whether refresh was successful.
     *
     * {@see refresh()}
     */
    protected function refreshInternal(array|ActiveRecordInterface $record = null): bool
    {
        if ($record === null || is_array($record)) {
            return false;
        }

        foreach ($this->attributes() as $name) {
            $this->setAttribute($name, $record->getAttribute($name));
        }

        $this->oldAttributes = $record->getOldAttributes();
        $this->related = [];
        $this->relationsDependencies = [];

        return true;
    }

    /**
     * {@see update()}
     *
     * @param array|null $attributes Attributes to update.
     *
     * @throws Exception
     * @throws NotSupportedException
     * @throws StaleObjectException
     *
     * @return int The number of rows affected.
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
            $lockValue = $this->$lock;

            $condition[$lock] = $lockValue;
            $values[$lock] = ++$lockValue;

            $rows = $this->updateAll($values, $condition);

            if ($rows === 0) {
                throw new StaleObjectException('The object being updated is outdated.');
            }

            $this->$lock = $lockValue;
        } else {
            $rows = $this->updateAll($values, $condition);
        }

        $this->oldAttributes = array_merge($this->oldAttributes ?? [], $values);

        return $rows;
    }

    private function bindModels(
        array $link,
        ActiveRecordInterface $foreignModel,
        ActiveRecordInterface $primaryModel
    ): void {
        /** @psalm-var string[] $link */
        foreach ($link as $fk => $pk) {
            /** @psalm-var mixed $value */
            $value = $primaryModel->$pk;

            if ($value === null) {
                throw new InvalidCallException(
                    'Unable to link active record: the primary key of ' . $primaryModel::class . ' is null.'
                );
            }

            /**
             * relation via array valued attribute
             *
             * @psalm-suppress MixedArrayAssignment
             */
            if (is_array($foreignModel->getAttribute($fk))) {
                /** @psalm-var mixed */
                $foreignModel->{$fk}[] = $value;
            } else {
                $foreignModel->setAttribute($fk, $value);
            }
        }

        $foreignModel->save();
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

    public function getTableName(): string
    {
        if ($this->tableName === '') {
            $this->tableName = '{{%' . DbStringHelper::pascalCaseToId(DbStringHelper::baseName(static::class)) . '}}';
        }

        return $this->tableName;
    }
}
