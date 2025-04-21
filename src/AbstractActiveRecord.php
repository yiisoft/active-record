<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Closure;
use ReflectionException;
use Throwable;
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
use function method_exists;
use function reset;

/**
 * ActiveRecord is the base class for classes representing relational data in terms of objects.
 *
 * See {@see ActiveRecord} for a concrete implementation.
 *
 * @psalm-import-type ModelClass from ActiveQueryInterface
 */
abstract class AbstractActiveRecord implements ActiveRecordInterface
{
    private array|null $oldValues = null;
    /**
     * @var ActiveRecordModelInterface[]|ActiveRecordModelInterface[][]|array[]|array[][]
     * @psalm-var array<string, ActiveRecordModelInterface|ActiveRecordModelInterface[]|array|array[]|null>
     */
    private array $related = [];
    /** @var string[][] */
    private array $relationsDependencies = [];

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

    public function __construct(protected ActiveRecordModelInterface $model)
    {
    }

    public function delete(): int
    {
        return $this->deleteInternal();
    }

    public function deleteAll(array $condition = [], array $params = []): int
    {
        $command = $this->model->db()->createCommand();
        $command->delete($this->model->tableName(), $condition, $params);

        return $command->execute();
    }

    public function equals(ActiveRecordModelInterface $record): bool
    {
        if ($this->isNewRecord() || $record->activeRecord()->isNewRecord()) {
            return false;
        }

        return $this->model->tableName() === $record->tableName()
            && $this->getPrimaryKey() === $record->activeRecord()->getPrimaryKey();
    }

    public function get(string $propertyName): mixed
    {
        return $this->model->propertyValues()[$propertyName] ?? null;
    }

    public function propertyValues(array|null $names = null, array $except = []): array
    {
        $names ??= $this->propertyNames();

        if (!empty($except)) {
            $names = array_diff($names, $except);
        }

        return array_intersect_key($this->model->propertyValues(), array_flip($names));
    }

    public function isNewRecord(): bool
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
                $this->model::class . ' does not have a primary key. You should either define a primary key for '
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
     * {@see ActiveRecordModelInterface::relationQuery()}
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
     * @param ActiveRecordModelInterface|Closure|string $class The class name of the related record, or an instance of
     * the related record, or a Closure to create an {@see ActiveRecordModelInterface} object.
     * @param array $link The primary-foreign key constraint. The keys of the array refer to the property names of
     * the record associated with the `$class` model, while the values of the array refer to the corresponding property
     * names in **this** active record class.
     *
     * @return ActiveQueryInterface The relational query object.
     *
     * @psalm-param ModelClass $class
     */
    public function hasMany(string|ActiveRecordModelInterface|Closure $class, array $link): ActiveQueryInterface
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
     * @param ActiveRecordModelInterface|Closure|string $class The class name of the related record, or an instance of
     * the related record, or a Closure to create an {@see ActiveRecordModelInterface} object.
     * @param array $link The primary-foreign key constraint. The keys of the array refer to the property names of
     * the record associated with the `$class` model, while the values of the array refer to the corresponding property
     * names in **this** active record class.
     *
     * @return ActiveQueryInterface The relational query object.
     *
     * @psalm-param ModelClass $class
     */
    public function hasOne(string|ActiveRecordModelInterface|Closure $class, array $link): ActiveQueryInterface
    {
        return $this->createRelationQuery($class, $link, false);
    }

    public function insert(array|null $propertyNames = null): bool
    {
        return $this->insertInternal($propertyNames);
    }

    /**
     * @param ActiveRecordModelInterface|Closure|string $modelClass The class name of the related record, or an instance of
     * the related record, or a Closure to create an {@see ActiveRecordModelInterface} object.
     *
     * @psalm-param ModelClass $modelClass
     */
    public function instantiateQuery(string|ActiveRecordModelInterface|Closure $modelClass): ActiveQueryInterface
    {
        if (method_exists($this->model, 'instantiateQuery')) {
            return $this->model->instantiateQuery($modelClass);
        }

        return new ActiveQuery($modelClass);
    }

    public function isChanged(): bool
    {
        return !empty($this->newValues());
    }

    public function isPropertyChanged(string $name): bool
    {
        $values = $this->model->propertyValues();

        if (empty($this->oldValues) || !array_key_exists($name, $this->oldValues)) {
            return array_key_exists($name, $values);
        }

        return !array_key_exists($name, $values) || $values[$name] !== $this->oldValues[$name];
    }

    public function isPropertyChangedNonStrict(string $name): bool
    {
        $values = $this->propertyValues();

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

    public function link(string $relationName, ActiveRecordModelInterface $model, array $extraColumns = []): void
    {
        $viaActiveRecord = null;
        $viaTable = null;
        $relation = $this->model->relationQuery($relationName);
        $via = $relation->getVia();
        $activeRecord = $model->activeRecord();

        if ($via !== null) {
            if ($this->isNewRecord() || $activeRecord->isNewRecord()) {
                throw new InvalidCallException(
                    'Unable to link models: the models being linked cannot be newly created.'
                );
            }

            if (is_array($via)) {
                [$viaName, $viaRelation] = $via;
                /** @psalm-var ActiveQueryInterface $viaRelation */
                $viaActiveRecord = $viaRelation->getModelInstance()->activeRecord();
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
                $columns[$b] = $activeRecord->get($a);
            }

            /**
             * @psalm-var string $k
             * @psalm-var mixed $v
             */
            foreach ($extraColumns as $k => $v) {
                /** @psalm-var mixed */
                $columns[$k] = $v;
            }

            if ($viaActiveRecord instanceof ActiveRecordInterface) {
                /**
                 * @psalm-var string $column
                 * @psalm-var mixed $value
                 */
                foreach ($columns as $column => $value) {
                    $viaActiveRecord->set($column, $value);
                }

                $viaActiveRecord->insert();
            } elseif (is_string($viaTable)) {
                $this->model->db()->createCommand()->insert($viaTable, $columns)->execute();
            }
        } else {
            $link = $relation->getLink();
            $p1 = $activeRecord->isPrimaryKey(array_keys($link));
            $p2 = $this->isPrimaryKey(array_values($link));

            if ($p1 && $p2) {
                if ($this->isNewRecord() && $activeRecord->isNewRecord()) {
                    throw new InvalidCallException('Unable to link models: at most one model can be newly created.');
                }

                if ($this->isNewRecord()) {
                    $this->bindModels(array_flip($link), $this, $activeRecord);
                } else {
                    $this->bindModels($link, $activeRecord, $this);
                }
            } elseif ($p1) {
                $this->bindModels(array_flip($link), $this, $activeRecord);
            } elseif ($p2) {
                $this->bindModels($link, $activeRecord, $this);
            } else {
                throw new InvalidCallException(
                    'Unable to link models: the link defining the relation does not involve any primary key.'
                );
            }
        }

        // Update lazily loaded related objects.
        if (!$relation->getMultiple()) {
            $this->related[$relationName] = $model;
        } elseif (isset($this->related[$relationName])) {
            /** @psalm-var ActiveRecordModelInterface[] $this->related[$relationName] */
            $indexBy = $relation->getIndexBy();
            if ($indexBy !== null) {
                if ($indexBy instanceof Closure) {
                    $index = $indexBy($model->propertyValues());
                } else {
                    $index = $activeRecord->get($indexBy);
                }

                if ($index !== null) {
                    $this->related[$relationName][$index] = $model;
                }
            } else {
                $this->related[$relationName][] = $model;
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
        if ($row instanceof ActiveRecordModelInterface) {
            $row = $row->propertyValues();
        }

        foreach ($row as $name => $value) {
            $this->model->populateProperty($name, $value);
            $this->oldValues[$name] = $value;
        }

        $this->related = [];
        $this->relationsDependencies = [];
    }

    public function populateRelation(string $name, array|ActiveRecordModelInterface|null $records): void
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
        $record = $this->instantiateQuery($this->model::class)->findOne($this->getPrimaryKey(true));

        return $this->refreshInternal($record);
    }

    public function relation(string $name): ActiveRecordModelInterface|array|null
    {
        if (array_key_exists($name, $this->related)) {
            return $this->related[$name];
        }

        return $this->retrieveRelation($name);
    }

    public function resetRelation(string $name): void
    {
        foreach ($this->relationsDependencies as &$relationNames) {
            unset($relationNames[$name]);
        }

        unset($this->related[$name]);
    }

    public function retrieveRelation(string $name): ActiveRecordModelInterface|array|null
    {
        /** @var ActiveQueryInterface $query */
        $query = $this->model->relationQuery($name);

        $this->setRelationDependencies($name, $query);

        return $this->related[$name] = $query->relatedRecords();
    }

    public function save(array|null $propertyNames = null): bool
    {
        if ($this->isNewRecord()) {
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

        $this->model->populateProperty($propertyName, $value);
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
            $this->model->populateProperty($name, $value);
        }
    }

    /**
     * Sets the value indicating whether the record is new.
     *
     * @param bool $value Whether the record is new and should be inserted when calling {@see save()}.
     *
     * @see isNewRecord()
     */
    public function setIsNewRecord(bool $value): void
    {
        $this->oldValues = $value ? null : $this->propertyValues();
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
            throw new InvalidArgumentException($this->model::class . ' has no property named "' . $propertyName . '".');
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
        $command = $this->model->db()->createCommand();

        $command->update($this->model->tableName(), $propertyValues, $condition, $params);

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

        if (empty($values) || $this->isNewRecord()) {
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
     * $customer = new Customer($db);
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

        $command = $this->model->db()->createCommand();
        $command->update($this->model->tableName(), $counters, $condition, $params);

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
     * $post = new Post($db);
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
            $this->model->populateProperty($name, $value);
            $this->oldValues[$name] = $value;
        }

        return true;
    }

    public function unlink(string $relationName, ActiveRecordModelInterface $model, bool $delete = false): void
    {
        $viaClass = null;
        $viaTable = null;
        $relation = $this->model->relationQuery($relationName);
        $viaRelation = $relation->getVia();
        $activeRecord = $model->activeRecord();

        if ($viaRelation !== null) {
            if (is_array($viaRelation)) {
                [$viaName, $viaRelation] = $viaRelation;
                /** @psalm-var ActiveQueryInterface $viaRelation */
                $viaClass = $viaRelation->getModelInstance()->activeRecord();
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
                    $columns[$b] = $activeRecord->get($a);
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
                $command = $this->model->db()->createCommand();
                if ($delete) {
                    $command->delete($viaTable, $columns)->execute();
                } else {
                    $command->update($viaTable, $nulls, $columns)->execute();
                }
            }
        } elseif ($relation instanceof ActiveQueryInterface) {
            if ($this->isPrimaryKey($relation->getLink())) {
                if ($delete) {
                    $activeRecord->delete();
                } else {
                    foreach ($relation->getLink() as $a => $b) {
                        $activeRecord->set($a, null);
                    }
                    $activeRecord->save();
                }
            } elseif ($activeRecord->isPrimaryKey(array_keys($relation->getLink()))) {
                foreach ($relation->getLink() as $a => $b) {
                    /** @psalm-var mixed $values */
                    $values = $this->get($b);
                    /** relation via array valued property */
                    if (is_array($values)) {
                        if (($key = array_search($activeRecord->get($a), $values)) !== false) {
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
            /** @psalm-var array<array-key, ActiveRecordModelInterface> $related */
            $related = $this->related[$relationName];
            foreach ($related as $a => $b) {
                if ($activeRecord->getPrimaryKey() === $b->activeRecord()->getPrimaryKey()) {
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
        $relation = $this->model->relationQuery($relationName);
        $viaRelation = $relation->getVia();

        if ($viaRelation !== null) {
            if (is_array($viaRelation)) {
                [$viaName, $viaRelation] = $viaRelation;
                /** @psalm-var ActiveQueryInterface $viaRelation */
                $viaClass = $viaRelation->getModelInstance()->activeRecord();
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
                $command = $this->model->db()->createCommand();
                if ($delete) {
                    $command->delete($viaTable, $condition)->execute();
                } else {
                    $command->update($viaTable, $nulls, $condition)->execute();
                }
            }
        } else {
            $relatedAr = $relation->getModelInstance()->activeRecord();

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
                    $relatedAr->deleteAll($condition);
                } else {
                    $relatedAr->updateAll($nulls, $condition);
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
        string $viaRelationName = null
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
     * @param ActiveRecordModelInterface|Closure|string $modelClass The class name of the related record.
     * @param array $link The primary-foreign key constraint.
     * @param bool $multiple Whether this query represents a relation to more than one record.
     *
     * @return ActiveQueryInterface The relational query object.
     *
     * @psalm-param ModelClass $modelClass

     * {@see hasOne()}
     * {@see hasMany()}
     */
    protected function createRelationQuery(string|ActiveRecordModelInterface|Closure $modelClass, array $link, bool $multiple): ActiveQueryInterface
    {
        return $this->instantiateQuery($modelClass)->primaryModel($this->model)->link($link)->multiple($multiple);
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

        if ($this->model instanceof OptimisticLockInterface) {
            $lock = $this->model->optimisticLockPropertyName();
            $condition[$lock] = $this->get($lock);

            $result = $this->deleteAll($condition);

            if ($result === 0) {
                throw new OptimisticLockException('The object being deleted is outdated.');
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
     * @param ActiveRecordModelInterface|array|null $record The record to take property values from.
     *
     * @return bool Whether refresh was successful.
     *
     * {@see refresh()}
     */
    protected function refreshInternal(array|ActiveRecordModelInterface|null $record = null): bool
    {
        if ($record === null || is_array($record)) {
            return false;
        }

        $activeRecord = $record->activeRecord();

        foreach ($this->propertyNames() as $name) {
            $this->model->populateProperty($name, $activeRecord->get($name));
        }

        $this->oldValues = $activeRecord->oldValues();
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

        if ($this->model instanceof OptimisticLockInterface) {
            $lock = $this->model->optimisticLockPropertyName();
            $lockValue = $this->get($lock);

            $condition[$lock] = $lockValue;
            $values[$lock] = ++$lockValue;

            $rows = $this->updateAll($values, $condition);

            if ($rows === 0) {
                throw new OptimisticLockException('The object being updated is outdated.');
            }

            $this->model->populateProperty($lock, $lockValue);
        } else {
            $rows = $this->updateAll($values, $condition);
        }

        $this->oldValues = array_merge($this->oldValues ?? [], $values);

        return $rows;
    }

    private function bindModels(
        array $link,
        ActiveRecordInterface $foreignActiveRecord,
        ActiveRecordInterface $primaryAcriveRecord
    ): void {
        /** @var string[] $link */
        foreach ($link as $fk => $pk) {
            /** @var self $primaryAcriveRecord */
            $value = $primaryAcriveRecord->get($pk);

            if ($value === null) {
                throw new InvalidCallException(
                    'Unable to link active record: the primary key of ' . $primaryAcriveRecord->model::class . ' is null.'
                );
            }

            /**
             * Relation via array valued property.
             */
            if (is_array($fkValue = $foreignActiveRecord->get($fk))) {
                $fkValue[] = $value;
                $foreignActiveRecord->set($fk, $fkValue);
            } else {
                $foreignActiveRecord->set($fk, $value);
            }
        }

        $foreignActiveRecord->save();
    }

    /**
     * Resets dependent related models checking if their links contain specific property.
     *
     * @param string $propertyName The changed property name.
     */
    public function resetDependentRelations(string $propertyName): void
    {
        if (!isset($this->relationsDependencies[$propertyName])) {
            return;
        }

        foreach ($this->relationsDependencies[$propertyName] as $relation) {
            unset($this->related[$relation]);
        }

        unset($this->relationsDependencies[$propertyName]);
    }
}
