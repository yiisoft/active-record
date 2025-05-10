<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Closure;
use ReflectionClass;
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
use function in_array;
use function is_array;
use function is_int;
use function ltrim;
use function preg_replace;
use function reset;
use function strtolower;

/**
 * ActiveRecord is the base class for classes representing relational data in terms of objects.
 *
 * See {@see ActiveRecord} for a concrete implementation.
 *
 * @psalm-import-type ARClass from ActiveQueryInterface
 */
abstract class AbstractActiveRecord implements ActiveRecordInterface
{
    private array|null $oldValues = null;
    /**
     * @var ActiveRecordInterface[]|ActiveRecordInterface[][]|array[]|array[][]
     * @psalm-var array<string, ActiveRecordInterface|ActiveRecordInterface[]|array|array[]|null>
     */
    private array $related = [];
    /** @var string[][] */
    private array $relationsDependencies = [];

    /**
     * Returns the available property values of an Active Record object.
     *
     * @return array
     *
     * @psalm-return array<string, mixed>
     */
    abstract protected function propertyValuesInternal(): array;

    /**
     * Inserts Active Record values into DB without considering transaction.
     *
     * @param array|null $propertyNames List of property names that need to be saved. Defaults to `null`, meaning all
     * changed property values will be saved. Only changed values will be saved.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws Throwable
     *
     * @return bool Whether the record inserted successfully.
     */
    abstract protected function insertInternal(array|null $propertyNames = null): bool;

    /**
     * Sets the value of the named property.
     *
     * @param string $name The property name.
     * @param mixed $value The property value.
     */
    abstract protected function populateProperty(string $name, mixed $value): void;

    public function delete(): int
    {
        return $this->deleteInternal();
    }

    public function deleteAll(array $condition = [], array $params = []): int
    {
        $command = $this->db()->createCommand();
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

    public function get(string $propertyName): mixed
    {
        return $this->propertyValuesInternal()[$propertyName] ?? null;
    }

    public function propertyValues(array|null $names = null, array $except = []): array
    {
        $names ??= $this->propertyNames();

        if (!empty($except)) {
            $names = array_diff($names, $except);
        }

        return array_intersect_key($this->propertyValuesInternal(), array_flip($names));
    }

    public function getIsNewRecord(): bool
    {
        return $this->oldValues === null;
    }

    /**
     * Returns the old value of the named property.
     *
     * If this record is the result of a query and the property is not loaded, `null` will be returned.
     *
     * @param string $propertyName The property name.
     *
     * @return mixed The old property value. `null` if the property is not loaded before or doesn't exist.
     *
     * @see hasProperty()
     */
    public function oldValue(string $propertyName): mixed
    {
        return $this->oldValues[$propertyName] ?? null;
    }

    /**
     * Returns the property values that have been modified since they're loaded or saved most recently.
     *
     * The comparison of new and old values uses `===`.
     *
     * @param array|null $propertyNames The names of the properties whose values may be returned if they're changed recently.
     * If null, {@see propertyNames()} will be used.
     *
     * @return array The changed property values (name-value pairs).
     */
    public function newValues(array|null $propertyNames = null): array
    {
        $values = $this->propertyValues($propertyNames);

        if ($this->oldValues === null) {
            return $values;
        }

        $result = array_diff_key($values, $this->oldValues);

        foreach (array_diff_key($values, $result) as $name => $value) {
            if ($value !== $this->oldValues[$name]) {
                $result[$name] = $value;
            }
        }

        return $result;
    }

    public function oldValues(): array
    {
        return $this->oldValues ?? [];
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

        if ($asArray === false && count($keys) === 1) {
            return $this->oldValues[$keys[0]] ?? null;
        }

        $values = [];

        foreach ($keys as $name) {
            $values[$name] = $this->oldValues[$name] ?? null;
        }

        return $values;
    }

    public function getPrimaryKey(bool $asArray = false): mixed
    {
        $keys = $this->primaryKey();

        if ($asArray === false && count($keys) === 1) {
            return $this->get($keys[0]);
        }

        $values = [];

        foreach ($keys as $name) {
            $values[$name] = $this->get($name);
        }

        return $values;
    }

    /**
     * Returns all populated related records.
     *
     * @return array An array of related records indexed by relation names.
     *
     * {@see relationQuery()}
     */
    public function getRelatedRecords(): array
    {
        return $this->related;
    }

    public function hasProperty(string $name): bool
    {
        return in_array($name, $this->propertyNames(), true);
    }

    /**
     * Declares a `has-many` relation.
     *
     * The declaration is returned in terms of a relational {@see ActiveQuery} instance through which the related
     * record can be queried and retrieved back.
     *
     * A `has-many` relation means that there are multiple related records matching the criteria set by this relation,
     * e.g., a customer has many orders.
     *
     * For example, to declare the `orders` relation for `Customer` class, you can write the following code in the
     * `Customer` class:
     *
     * ```php
     * public function getOrdersQuery()
     * {
     *     return $this->hasMany(Order::className(), ['customer_id' => 'id']);
     * }
     * ```
     *
     * Note that the `customer_id` key in the `$link` parameter refers to a property name in the related
     * class `Order`, while the 'id' value refers to a property name in the current active record class.
     *
     * Call methods declared in {@see ActiveQuery} to further customize the relation.
     *
     * @param ActiveRecordInterface|Closure|string $class The class name of the related record, or an instance of
     * the related record, or a Closure to create an {@see ActiveRecordInterface} object.
     * @param array $link The primary-foreign key constraint. The keys of the array refer to the property names of
     * the record associated with the `$class` model, while the values of the array refer to the corresponding property
     * names in **this** active record class.
     *
     * @return ActiveQueryInterface The relational query object.
     *
     * @psalm-param ARClass $class
     */
    public function hasMany(string|ActiveRecordInterface|Closure $class, array $link): ActiveQueryInterface
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
     * For example, to declare the `country` relation for `Customer` class, you can write the following code in the
     * `Customer` class:
     *
     * ```php
     * public function getCountryQuery()
     * {
     *     return $this->hasOne(Country::className(), ['id' => 'country_id']);
     * }
     * ```
     *
     * Note that the `id` key in the `$link` parameter refers to a property name in the related class
     * `Country`, while the `country_id` value refers to a property name in the current active record class.
     *
     * Call methods declared in {@see ActiveQuery} to further customize the relation.
     *
     * @param ActiveRecordInterface|Closure|string $class The class name of the related record, or an instance of
     * the related record, or a Closure to create an {@see ActiveRecordInterface} object.
     * @param array $link The primary-foreign key constraint. The keys of the array refer to the property names of
     * the record associated with the `$class` model, while the values of the array refer to the corresponding property
     * names in **this** active record class.
     *
     * @return ActiveQueryInterface The relational query object.
     *
     * @psalm-param ARClass $class
     */
    public function hasOne(string|ActiveRecordInterface|Closure $class, array $link): ActiveQueryInterface
    {
        return $this->createRelationQuery($class, $link, false);
    }

    public function insert(array|null $propertyNames = null): bool
    {
        return $this->insertInternal($propertyNames);
    }

    /**
     * @param ActiveRecordInterface|Closure|string|null $arClass The class name of the related record, or an instance of
     * the related record, or a Closure to create an {@see ActiveRecordInterface} object. If `null`, the current model
     * will be used.
     *
     * @psalm-param ARClass $arClass
     */
    public function query(ActiveRecordInterface|Closure|null|string $arClass = null): ActiveQueryInterface
    {
        return new ActiveQuery($arClass ?? $this);
    }

    public function isChanged(): bool
    {
        return !empty($this->newValues());
    }

    public function isPropertyChanged(string $name): bool
    {
        $values = $this->propertyValuesInternal();

        if (empty($this->oldValues) || !array_key_exists($name, $this->oldValues)) {
            return array_key_exists($name, $values);
        }

        return !array_key_exists($name, $values) || $values[$name] !== $this->oldValues[$name];
    }

    public function isPropertyChangedNonStrict(string $name): bool
    {
        $values = $this->propertyValuesInternal();

        if (empty($this->oldValues) || !array_key_exists($name, $this->oldValues)) {
            return array_key_exists($name, $values);
        }

        return !array_key_exists($name, $values) || $values[$name] != $this->oldValues[$name];
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

    public function link(string $relationName, ActiveRecordInterface $arClass, array $extraColumns = []): void
    {
        $viaClass = null;
        $viaTable = null;
        $relation = $this->relationQuery($relationName);
        $via = $relation->getVia();

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
            } else {
                $viaRelation = $via;
                $from = $via->getFrom();
                /** @psalm-var string $viaTable */
                $viaTable = reset($from);
            }

            $columns = [];

            $viaLink = $viaRelation->getLink();

            /**
             * @psalm-var string $a
             * @psalm-var string $b
             */
            foreach ($viaLink as $a => $b) {
                /** @psalm-var mixed */
                $columns[$a] = $this->get($b);
            }

            $link = $relation->getLink();

            /**
             * @psalm-var string $a
             * @psalm-var string $b
             */
            foreach ($link as $a => $b) {
                /** @psalm-var mixed */
                $columns[$b] = $arClass->get($a);
            }

            /**
             * @psalm-var string $k
             * @psalm-var mixed $v
             */
            foreach ($extraColumns as $k => $v) {
                /** @psalm-var mixed */
                $columns[$k] = $v;
            }

            if ($viaClass instanceof ActiveRecordInterface) {
                /**
                 * @psalm-var string $column
                 * @psalm-var mixed $value
                 */
                foreach ($columns as $column => $value) {
                    $viaClass->set($column, $value);
                }

                $viaClass->insert();
            } elseif (is_string($viaTable)) {
                $this->db()->createCommand()->insert($viaTable, $columns)->execute();
            }
        } else {
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

        // Update lazily loaded related objects.
        if (!$relation->getMultiple()) {
            $this->related[$relationName] = $arClass;
        } elseif (isset($this->related[$relationName])) {
            /** @psalm-var ActiveRecordInterface[] $this->related[$relationName] */
            $indexBy = $relation->getIndexBy();
            if ($indexBy !== null) {
                if ($indexBy instanceof Closure) {
                    $index = $indexBy($arClass->propertyValues());
                } else {
                    $index = $arClass->get($indexBy);
                }

                if ($index !== null) {
                    $this->related[$relationName][$index] = $arClass;
                }
            } else {
                $this->related[$relationName][] = $arClass;
            }
        }
    }

    /**
     * Marks a property as changed.
     *
     * This method may be called to force updating a record when calling {@see update()}, even if there is no change
     * being made to the record.
     *
     * @param string $name The property name.
     */
    public function markPropertyChanged(string $name): void
    {
        if ($this->oldValues !== null && $name !== '') {
            unset($this->oldValues[$name]);
        }
    }

    /**
     * Populates an active record object using a row of data from the database/storage.
     *
     * This is an internal method meant to be called to create active record objects after fetching data from the
     * database. It is mainly used by {@see ActiveQuery} to populate the query results into active records.
     *
     * @param array|object $row Property values (name => value).
     */
    public function populateRecord(array|object $row): void
    {
        if ($row instanceof ActiveRecordInterface) {
            $row = $row->propertyValues();
        }

        foreach ($row as $name => $value) {
            $this->populateProperty($name, $value);
            $this->oldValues[$name] = $value;
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
        $record = $this->query($this)->findByPk($this->getPrimaryKey(true));

        return $this->refreshInternal($record);
    }

    public function relation(string $name): ActiveRecordInterface|array|null
    {
        if (array_key_exists($name, $this->related)) {
            return $this->related[$name];
        }

        return $this->retrieveRelation($name);
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        throw new InvalidArgumentException(static::class . ' has no relation named "' . $name . '".');
    }

    public function resetRelation(string $name): void
    {
        foreach ($this->relationsDependencies as &$relationNames) {
            unset($relationNames[$name]);
        }

        unset($this->related[$name]);
    }

    protected function retrieveRelation(string $name): ActiveRecordInterface|array|null
    {
        /** @var ActiveQueryInterface $query */
        $query = $this->relationQuery($name);

        $this->setRelationDependencies($name, $query);

        return $this->related[$name] = $query->relatedRecords();
    }

    public function save(array|null $propertyNames = null): bool
    {
        if ($this->getIsNewRecord()) {
            return $this->insert($propertyNames);
        }

        $this->update($propertyNames);

        return true;
    }

    public function set(string $propertyName, mixed $value): void
    {
        if (
            isset($this->relationsDependencies[$propertyName])
            && ($value === null || $this->get($propertyName) !== $value)
        ) {
            $this->resetDependentRelations($propertyName);
        }

        $this->populateProperty($propertyName, $value);
    }

    /**
     * Sets the property values in a massive way.
     *
     * @param array $values Property values (name => value) to be assigned to the model.
     *
     * @see propertyNames()
     */
    public function populateProperties(array $values): void
    {
        $values = array_intersect_key($values, array_flip($this->propertyNames()));

        /** @psalm-var mixed $value */
        foreach ($values as $name => $value) {
            $this->populateProperty($name, $value);
        }
    }

    /**
     * Sets the value indicating whether the record is new.
     *
     * @param bool $value Whether the record is new and should be inserted when calling {@see save()}.
     *
     * @see getIsNewRecord()
     */
    public function setIsNewRecord(bool $value): void
    {
        $this->oldValues = $value ? null : $this->propertyValuesInternal();
    }

    /**
     * Sets the old value of the named property.
     *
     * @param string $propertyName The property name.
     *
     * @throws InvalidArgumentException If the named property doesn't exist.
     *
     * @see hasProperty()
     */
    public function assignOldValue(string $propertyName, mixed $value): void
    {
        if (isset($this->oldValues[$propertyName]) || $this->hasProperty($propertyName)) {
            $this->oldValues[$propertyName] = $value;
        } else {
            throw new InvalidArgumentException(static::class . ' has no property named "' . $propertyName . '".');
        }
    }

    /**
     * Sets the old property values.
     *
     * All existing old property values will be discarded.
     *
     * @param array|null $propertyValues Old property values (name => value) to be set. If set to `null` this record
     * is {@see isNewRecord() new}.
     */
    public function assignOldValues(array|null $propertyValues = null): void
    {
        $this->oldValues = $propertyValues;
    }

    public function update(array|null $propertyNames = null): int
    {
        return $this->updateInternal($propertyNames);
    }

    public function updateAll(array $propertyValues, array|string $condition = [], array $params = []): int
    {
        $command = $this->db()->createCommand();

        $command->update($this->getTableName(), $propertyValues, $condition, $params);

        return $command->execute();
    }

    public function updateProperties(array $properties): int
    {
        $names = [];

        foreach ($properties as $name => $value) {
            if (is_int($name)) {
                $names[] = $value;
            } else {
                $this->set($name, $value);
                $names[] = $name;
            }
        }

        $values = $this->newValues($names);

        if (empty($values) || $this->getIsNewRecord()) {
            return 0;
        }

        $rows = $this->updateAll($values, $this->getOldPrimaryKey(true));

        $this->oldValues = array_merge($this->oldValues ?? [], $values);

        return $rows;
    }

    /**
     * Updates the whole table using the provided counters and condition.
     *
     * For example, to increment all customers' age by 1:
     *
     * ```php
     * $customer = new Customer();
     * $customer->updateAllCounters(['age' => 1]);
     * ```
     *
     * Note that this method will not trigger any events.
     *
     * @param array $counters The counters to be updated (property name => increment value).
     * Use negative values if you want to decrement the counters.
     * @param array|string $condition The conditions that will be put in the `WHERE` part of the `UPDATE` SQL.
     * Please refer to {@see Query::where()} on how to specify this parameter.
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

        $command = $this->db()->createCommand();
        $command->update($this->getTableName(), $counters, $condition, $params);

        return $command->execute();
    }

    /**
     * Updates one or several counters for the current AR object.
     *
     * Note that this method differs from {@see updateAllCounters()} in that it only saves counters for the current AR
     * object.
     *
     * An example usage is as follows:
     *
     * ```php
     * $post = new Post();
     * $post->updateCounters(['view_count' => 1]);
     * ```
     *
     * @param array $counters The counters to be updated (property name => increment value), use negative values if you
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
            $value += $this->get($name) ?? 0;
            $this->populateProperty($name, $value);
            $this->oldValues[$name] = $value;
        }

        return true;
    }

    public function unlink(string $relationName, ActiveRecordInterface $arClass, bool $delete = false): void
    {
        $viaClass = null;
        $viaTable = null;
        $relation = $this->relationQuery($relationName);
        $viaRelation = $relation->getVia();

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
                    $columns[$a] = $this->get($b);
                }

                $link = $relation->getLink();

                foreach ($link as $a => $b) {
                    /** @psalm-var mixed */
                    $columns[$b] = $arClass->get($a);
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
                $command = $this->db()->createCommand();
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
                        $arClass->set($a, null);
                    }
                    $arClass->save();
                }
            } elseif ($arClass->isPrimaryKey(array_keys($relation->getLink()))) {
                foreach ($relation->getLink() as $a => $b) {
                    /** @psalm-var mixed $values */
                    $values = $this->get($b);
                    /** relation via array valued property */
                    if (is_array($values)) {
                        if (($key = array_search($arClass->get($a), $values)) !== false) {
                            unset($values[$key]);
                            $this->set($b, array_values($values));
                        }
                    } else {
                        $this->set($b, null);
                    }
                }
                $delete ? $this->delete() : $this->save();
            } else {
                throw new InvalidCallException('Unable to unlink models: the link does not involve any primary key.');
            }
        }

        if (!$relation->getMultiple()) {
            unset($this->related[$relationName]);
        } elseif (isset($this->related[$relationName]) && is_array($this->related[$relationName])) {
            /** @psalm-var array<array-key, ActiveRecordInterface> $related */
            $related = $this->related[$relationName];
            foreach ($related as $a => $b) {
                if ($arClass->getPrimaryKey() === $b->getPrimaryKey()) {
                    unset($this->related[$relationName][$a]);
                }
            }
        }
    }

    /**
     * Destroys the relationship in the current model.
     *
     * The active record with the foreign key of the relationship will be deleted if `$delete` is `true`. Otherwise, the
     * foreign key will be set `null` and the model will be saved without validation.
     *
     * To destroy the relationship without removing records, make sure your keys can be set to `null`.
     *
     * @param string $relationName The case-sensitive name of the relationship.
     * @param bool $delete Whether to delete the model that contains the foreign key.
     *
     * @throws Exception
     * @throws ReflectionException
     * @throws StaleObjectException
     * @throws Throwable
     */
    public function unlinkAll(string $relationName, bool $delete = false): void
    {
        $viaClass = null;
        $viaTable = null;
        $relation = $this->relationQuery($relationName);
        $viaRelation = $relation->getVia();

        if ($viaRelation !== null) {
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
                    $condition[$a] = $this->get($b);
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
                $command = $this->db()->createCommand();
                if ($delete) {
                    $command->delete($viaTable, $condition)->execute();
                } else {
                    $command->update($viaTable, $nulls, $condition)->execute();
                }
            }
        } else {
            $relatedModel = $relation->getARInstance();

            $link = $relation->getLink();
            if (!$delete && count($link) === 1 && is_array($this->get($b = reset($link)))) {
                /** relation via array valued property */
                $this->set($b, []);
                $this->save();
            } else {
                $nulls = [];
                $condition = [];

                foreach ($relation->getLink() as $a => $b) {
                    $nulls[$a] = null;
                    /** @psalm-var mixed */
                    $condition[$a] = $this->get($b);
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

        unset($this->related[$relationName]);
    }

    /**
     * Sets relation dependencies for a property.
     *
     * @param string $name Property name.
     * @param ActiveQueryInterface $relation Relation instance.
     * @param string|null $viaRelationName Intermediate relation.
     */
    private function setRelationDependencies(
        string $name,
        ActiveQueryInterface $relation,
        string|null $viaRelationName = null
    ): void {
        $via = $relation->getVia();

        if (empty($via)) {
            foreach ($relation->getLink() as $propertyName) {
                $this->relationsDependencies[$propertyName][$name] = $name;
                if ($viaRelationName !== null) {
                    $this->relationsDependencies[$propertyName][] = $viaRelationName;
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
     * @param ActiveRecordInterface|Closure|string $arClass The class name of the related record.
     * @param array $link The primary-foreign key constraint.
     * @param bool $multiple Whether this query represents a relation to more than one record.
     *
     * @return ActiveQueryInterface The relational query object.
     *
     * @psalm-param ARClass $arClass

     * {@see hasOne()}
     * {@see hasMany()}
     */
    protected function createRelationQuery(string|ActiveRecordInterface|Closure $arClass, array $link, bool $multiple): ActiveQueryInterface
    {
        return $this->query($arClass)->primaryModel($this)->link($link)->multiple($multiple);
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
         * We don't check the return value of deleteAll() because it is possible the record is already deleted in
         * the database and thus the method will return 0
         */
        $condition = $this->getOldPrimaryKey(true);

        if ($this instanceof OptimisticLockInterface) {
            $lock = $this->optimisticLockPropertyName();
            $condition[$lock] = $this->get($lock);

            $result = $this->deleteAll($condition);

            if ($result === 0) {
                throw new OptimisticLockException(
                    'The object being deleted is outdated.'
                );
            }
        } else {
            $result = $this->deleteAll($condition);
        }

        $this->assignOldValues();

        return $result;
    }

    /**
     * Repopulates this active record with the latest data from a newly fetched instance.
     *
     * @param ActiveRecordInterface|array|null $record The record to take property values from.
     *
     * @return bool Whether refresh was successful.
     *
     * {@see refresh()}
     */
    protected function refreshInternal(array|ActiveRecordInterface|null $record = null): bool
    {
        if ($record === null || is_array($record)) {
            return false;
        }

        foreach ($this->propertyNames() as $name) {
            $this->populateProperty($name, $record->get($name));
        }

        $this->oldValues = $record->oldValues();
        $this->related = [];
        $this->relationsDependencies = [];

        return true;
    }

    /**
     * {@see update()}
     *
     * @param array|null $propertyNames Property names to update.
     *
     * @throws Exception
     * @throws NotSupportedException
     * @throws StaleObjectException
     *
     * @return int The number of rows affected.
     */
    protected function updateInternal(array|null $propertyNames = null): int
    {
        $values = $this->newValues($propertyNames);

        if (empty($values)) {
            return 0;
        }

        $condition = $this->getOldPrimaryKey(true);

        if ($this instanceof OptimisticLockInterface) {
            $lock = $this->optimisticLockPropertyName();
            $lockValue = $this->get($lock);

            $condition[$lock] = $lockValue;
            $values[$lock] = ++$lockValue;

            $rows = $this->updateAll($values, $condition);

            if ($rows === 0) {
                throw new OptimisticLockException(
                    'The object being updated is outdated.'
                );
            }

            $this->populateProperty($lock, $lockValue);
        } else {
            $rows = $this->updateAll($values, $condition);
        }

        $this->oldValues = array_merge($this->oldValues ?? [], $values);

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
            $value = $primaryModel->get($pk);

            if ($value === null) {
                throw new InvalidCallException(
                    'Unable to link active record: the primary key of ' . $primaryModel::class . ' is null.'
                );
            }

            /**
             * Relation via array valued property.
             */
            if (is_array($fkValue = $foreignModel->get($fk))) {
                /** @psalm-var mixed */
                $fkValue[] = $value;
                $foreignModel->set($fk, $fkValue);
            } else {
                $foreignModel->set($fk, $value);
            }
        }

        $foreignModel->save();
    }

    protected function hasDependentRelations(string $propertyName): bool
    {
        return isset($this->relationsDependencies[$propertyName]);
    }

    /**
     * Resets dependent related models checking if their links contain specific property.
     *
     * @param string $propertyName The changed property name.
     */
    protected function resetDependentRelations(string $propertyName): void
    {
        foreach ($this->relationsDependencies[$propertyName] as $relation) {
            unset($this->related[$relation]);
        }

        unset($this->relationsDependencies[$propertyName]);
    }

    public function getTableName(): string
    {
        $name = (new ReflectionClass($this))->getShortName();
        /** @var string $name */
        $name = preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '_$0', $name);
        $name = strtolower(ltrim($name, '_'));

        return '{{%' . $name . '}}';
    }

    public function db(): ConnectionInterface
    {
        return ConnectionProvider::get();
    }
}
