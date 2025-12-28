<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Closure;
use DateTimeInterface;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use Throwable;
use Yiisoft\ActiveRecord\Internal\ArArrayHelper;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidCallException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Expression\ExpressionInterface;
use Yiisoft\Db\Query\QueryInterface;

use function array_diff;
use function array_diff_key;
use function array_fill_keys;
use function array_flip;
use function array_intersect;
use function array_intersect_key;
use function array_is_list;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_search;
use function array_values;
use function count;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function ltrim;
use function preg_replace;
use function reset;
use function strtolower;

/**
 * ActiveRecord is the base class for classes representing relational data in terms of objects.
 *
 * See {@see ActiveRecord} for a concrete implementation.
 *
 * @psalm-import-type ModelClass from ActiveQuery
 * @psalm-import-type RawFrom from QueryInterface
 */
abstract class AbstractActiveRecord implements ActiveRecordInterface
{
    private ?array $oldValues = null;
    /**
     * @var ActiveRecordInterface[]|ActiveRecordInterface[][]|array[]|array[][]
     * @psalm-var array<string, ActiveRecordInterface|ActiveRecordInterface[]|array|array[]|null>
     */
    private array $related = [];
    /** @var string[][] */
    private array $relationsDependencies = [];

    public function createQuery(ActiveRecordInterface|string|null $modelClass = null): ActiveQueryInterface
    {
        return static::query($modelClass ?? $this);
    }

    public function delete(): int
    {
        return $this->deleteInternal();
    }

    public function deleteAll(array $condition = [], array $params = []): int
    {
        $command = $this->db()->createCommand();
        $command->delete($this->tableName(), $condition, $params);

        return $command->execute();
    }

    public function equals(ActiveRecordInterface $record): bool
    {
        if ($this->isNew() || $record->isNew()) {
            return false;
        }

        return $this->tableName() === $record->tableName() && $this->primaryKeyValues() === $record->primaryKeyValues();
    }

    public function get(string $propertyName): mixed
    {
        return $this->propertyValuesInternal()[$propertyName] ?? null;
    }

    public function propertyValues(?array $names = null, array $except = []): array
    {
        $names ??= $this->propertyNames();

        if (!empty($except)) {
            $names = array_diff($names, $except);
        }

        return array_intersect_key($this->propertyValuesInternal(), array_flip($names));
    }

    public function isNew(): bool
    {
        return $this->oldValues === null;
    }

    public function oldValue(string $propertyName): mixed
    {
        return $this->oldValues[$propertyName] ?? null;
    }

    public function newValues(?array $propertyNames = null): array
    {
        $currentValues = $this->propertyValues($propertyNames);
        if (($oldValues = $this->oldValues()) === []) {
            return $currentValues;
        }

        $newValues = array_diff_key($currentValues, $oldValues);

        foreach (array_diff_key($currentValues, $newValues) as $name => $newValue) {
            if ($newValue instanceof DateTimeInterface) {
                if ($oldValues[$name] === null ||
                    $newValue->format('Y-m-d\TH:i:s.uP') != $oldValues[$name]->format('Y-m-d\TH:i:s.uP')) {
                    $newValues[$name] = $newValue;
                }
            } elseif ($newValue !== $oldValues[$name]) {
                $newValues[$name] = $newValue;
            }
        }

        return $newValues;
    }

    public function oldValues(): array
    {
        return $this->oldValues ?? [];
    }

    public function primaryKeyOldValue(): float|int|string|null
    {
        $keys = $this->primaryKey();

        /**
         * @var float|int|string|null We assume primary key old value always is float, int, string or null.
         */
        return match (count($keys)) {
            1 => $this->oldValues[$keys[0]] ?? null,
            0 => throw new LogicException(
                static::class . ' does not have a primary key. You should either define a primary key for '
                . $this->tableName() . ' table or override the primaryKey() method.',
            ),
            default => throw new LogicException(
                static::class . ' has multiple primary keys. Use primaryKeyOldValues() method instead.',
            ),
        };
    }

    public function primaryKeyOldValues(): array
    {
        $keys = $this->primaryKey();

        if (empty($keys)) {
            throw new LogicException(
                static::class . ' does not have a primary key. You should either define a primary key for '
                . $this->tableName() . ' table or override the primaryKey() method.',
            );
        }

        $values = [];
        foreach ($keys as $name) {
            /**
             * @var bool|float|int|string|null We assume primary key old values always are scalar or null.
             */
            $values[$name] = $this->oldValues[$name] ?? null;
        }
        return $values;
    }

    public function primaryKeyValue(): float|int|string|null
    {
        $keys = $this->primaryKey();

        /**
         * @var float|int|string|null We assume primary key value always is float, int, string or null.
         */
        return match (count($keys)) {
            1 => $this->get($keys[0]),
            0 => throw new LogicException(
                static::class . ' does not have a primary key. You should either define a primary key for '
                . $this->tableName() . ' table or override the primaryKey() method.',
            ),
            default => throw new LogicException(
                static::class . ' has multiple primary keys. Use primaryKeyValues() method instead.',
            ),
        };
    }

    public function primaryKeyValues(): array
    {
        $keys = $this->primaryKey();

        if (empty($keys)) {
            throw new LogicException(
                static::class . ' does not have a primary key. You should either define a primary key for '
                . $this->tableName() . ' table or override the primaryKey() method.',
            );
        }

        $values = [];
        foreach ($keys as $name) {
            /**
             * @var bool|float|int|string|null We assume primary key old values always are scalar or null.
             */
            $values[$name] = $this->get($name);
        }
        return $values;
    }

    public function relatedRecords(): array
    {
        return $this->related;
    }

    public function hasProperty(string $name): bool
    {
        return in_array($name, $this->propertyNames(), true);
    }

    public function hasMany(ActiveRecordInterface|string $modelClass, array $link): ActiveQueryInterface
    {
        return $this->createRelationQuery($modelClass, $link, true);
    }

    public function hasOne(ActiveRecordInterface|string $modelClass, array $link): ActiveQueryInterface
    {
        return $this->createRelationQuery($modelClass, $link, false);
    }

    public function insert(?array $properties = null): void
    {
        $this->insertInternal($properties);
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

    public function link(string $relationName, ActiveRecordInterface $linkModel, array $extraColumns = []): void
    {
        $viaModel = null;
        $viaTable = null;
        $relation = $this->relationQuery($relationName);
        $via = $relation->getVia();

        if ($via !== null) {
            if ($this->isNew() || $linkModel->isNew()) {
                throw new InvalidCallException(
                    'Unable to link models: the models being linked cannot be newly created.',
                );
            }

            if (is_array($via)) {
                [$viaName, $viaRelation] = $via;
                $viaModel = $viaRelation->getModel();
                // unset $viaName so that it can be reloaded to reflect the change.
                unset($this->related[$viaName]);
            } else {
                $viaRelation = $via;
                $from = $via->getFrom();
                $viaTable = reset($from);
            }

            $columns = [];

            $viaLink = $viaRelation->getLink();

            foreach ($viaLink as $a => $b) {
                $columns[$a] = $this->get($b);
            }

            $link = $relation->getLink();

            foreach ($link as $a => $b) {
                $columns[$b] = $linkModel->get($a);
            }

            foreach ($extraColumns as $k => $v) {
                $columns[$k] = $v;
            }

            if ($viaModel !== null) {
                foreach ($columns as $column => $value) {
                    $viaModel->set($column, $value);
                }

                $viaModel->insert();
            } elseif (is_string($viaTable)) {
                $this->db()->createCommand()->insert($viaTable, $columns)->execute();
            }
        } else {
            $link = $relation->getLink();
            $p1 = $linkModel->isPrimaryKey(array_keys($link));
            $p2 = $this->isPrimaryKey(array_values($link));

            if ($p1 && $p2) {
                if ($this->isNew() && $linkModel->isNew()) {
                    throw new InvalidCallException('Unable to link models: at most one model can be newly created.');
                }

                if ($this->isNew()) {
                    $this->bindModels(array_flip($link), $this, $linkModel);
                } else {
                    $this->bindModels($link, $linkModel, $this);
                }
            } elseif ($p1) {
                $this->bindModels(array_flip($link), $this, $linkModel);
            } elseif ($p2) {
                $this->bindModels($link, $linkModel, $this);
            } else {
                throw new InvalidCallException(
                    'Unable to link models: the link defining the relation does not involve any primary key.',
                );
            }
        }

        // Update lazily loaded related objects.
        if (!$relation->isMultiple()) {
            $this->related[$relationName] = $linkModel;
        } elseif (isset($this->related[$relationName])) {
            /**
             * Related records are already an array.
             * @psalm-var array<string, ActiveRecordInterface[]|array[]> $this->related[$relationName]
             */
            $indexBy = $relation->getIndexBy();
            if ($indexBy !== null) {
                /**
                 * We assume that the index is always string, int or null.
                 * @var int|string|null $index
                 */
                $index = $indexBy instanceof Closure
                    ? $indexBy($linkModel)
                    : $linkModel->get($indexBy);
                if ($index !== null) {
                    $this->related[$relationName][$index] = $linkModel;
                }
            } else {
                $this->related[$relationName][] = $linkModel;
            }
        }
    }

    public function markPropertyChanged(string $name): void
    {
        if ($this->oldValues !== null && $name !== '') {
            unset($this->oldValues[$name]);
        }
    }

    public function populateRecord(array|object $row): static
    {
        $row = ArArrayHelper::toArray($row);

        foreach ($row as $name => $value) {
            $this->populateProperty($name, $value);
            $this->oldValues[$name] = $value;
        }

        $this->related = [];
        $this->relationsDependencies = [];
        return $this;
    }

    public function populateRelation(string $name, array|ActiveRecordInterface|null $records): void
    {
        foreach ($this->relationsDependencies as &$relationNames) {
            unset($relationNames[$name]);
        }

        $this->related[$name] = $records;
    }

    public static function query(ActiveRecordInterface|string|null $modelClass = null): ActiveQueryInterface
    {
        return new ActiveQuery($modelClass ?? static::class);
    }

    /**
     * Repopulates this active record with the latest data.
     *
     * @return bool Whether the row still exists in the database. If `true`, the latest data will be populated to this
     * active record. Otherwise, this record will remain unchanged.
     */
    public function refresh(): bool
    {
        $record = $this->createQuery()->findByPk($this->primaryKeyOldValues());

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

    public function save(?array $properties = null): void
    {
        if ($this->isNew()) {
            $this->insert($properties);
            return;
        }

        $this->update($properties);
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

    public function populateProperties(array $values): void
    {
        $values = array_intersect_key($values, array_flip($this->propertyNames()));
        foreach ($values as $name => $value) {
            $this->populateProperty($name, $value);
        }
    }

    public function markAsNew(): void
    {
        $this->oldValues = null;
    }

    public function markAsExisting(): void
    {
        $this->oldValues = $this->propertyValuesInternal();
    }

    public function assignOldValue(string $propertyName, mixed $value): void
    {
        if (isset($this->oldValues[$propertyName]) || $this->hasProperty($propertyName)) {
            $this->oldValues[$propertyName] = $value;
        } else {
            throw new InvalidArgumentException(static::class . ' has no property named "' . $propertyName . '".');
        }
    }

    public function assignOldValues(?array $propertyValues = null): void
    {
        $this->oldValues = $propertyValues;
    }

    public function update(?array $properties = null): int
    {
        return $this->updateInternal($properties);
    }

    public function updateAll(array $propertyValues, array|string $condition = [], array|ExpressionInterface|string|null $from = null, array $params = []): int
    {
        $command = $this->db()->createCommand();

        $command->update($this->tableName(), $propertyValues, $condition, $from, $params);

        return $command->execute();
    }

    public function updateAllCounters(
        array $counters,
        array|string $condition = '',
        array|ExpressionInterface|string|null $from = null,
        array $params = [],
    ): int {
        $n = 0;
        foreach ($counters as $name => $value) {
            $counters[$name] = new Expression("[[$name]]+:bp$n", [":bp$n" => $value]);
            $n++;
        }

        return $this->db()
            ->createCommand()
            ->update($this->tableName(), $counters, $condition, $from, $params)
            ->execute();
    }

    public function updateCounters(array $counters): void
    {
        if ($this->isNew()) {
            throw new LogicException('Updating counters is not possible for new records.');
        }

        $this->updateAllCounters($counters, $this->primaryKeyOldValues());

        foreach ($counters as $name => $value) {
            /**
             * @psalm-suppress MixedOperand We assume that the counter value is always an integer.
             */
            $value += $this->get($name) ?? 0;
            $this->populateProperty($name, $value);
            $this->oldValues[$name] = $value;
        }
    }

    public function upsert(?array $insertProperties = null, array|bool $updateProperties = true): void
    {
        $this->upsertInternal($insertProperties, $updateProperties);
    }

    public function unlink(string $relationName, ActiveRecordInterface $linkedModel, bool $delete = false): void
    {
        $viaModel = null;
        $viaTable = null;
        $relation = $this->relationQuery($relationName);
        $viaRelation = $relation->getVia();

        if ($viaRelation !== null) {
            if (is_array($viaRelation)) {
                [$viaName, $viaRelation] = $viaRelation;
                $viaModel = $viaRelation->getModel();
                unset($this->related[$viaName]);
            }

            $columns = [];
            $nulls = [];

            if ($viaRelation instanceof ActiveQueryInterface) {
                $from = $viaRelation->getFrom();
                $viaTable = reset($from);

                foreach ($viaRelation->getLink() as $a => $b) {
                    $columns[$a] = $this->get($b);
                }

                $link = $relation->getLink();

                foreach ($link as $a => $b) {
                    $columns[$b] = $linkedModel->get($a);
                }

                $nulls = array_fill_keys(array_keys($columns), null);

                if ($viaRelation->getOn() !== null) {
                    $columns = ['and', $columns, $viaRelation->getOn()];
                }
            }

            if ($viaModel !== null) {
                if ($delete) {
                    $viaModel->deleteAll($columns);
                } else {
                    $viaModel->updateAll($nulls, $columns);
                }
            } elseif (is_string($viaTable)) {
                $command = $this->db()->createCommand();
                if ($delete) {
                    $command->delete($viaTable, $columns)->execute();
                } else {
                    $command->update($viaTable, $nulls, $columns)->execute();
                }
            }
        } else {
            if ($this->isPrimaryKey($relation->getLink())) {
                if ($delete) {
                    $linkedModel->delete();
                } else {
                    foreach ($relation->getLink() as $a => $b) {
                        $linkedModel->set($a, null);
                    }
                    $linkedModel->save();
                }
            } elseif ($linkedModel->isPrimaryKey(array_keys($relation->getLink()))) {
                foreach ($relation->getLink() as $a => $b) {
                    $values = $this->get($b);
                    // relation via array valued property
                    if (is_array($values)) {
                        if (($key = array_search($linkedModel->get($a), $values)) !== false) {
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

        if (!$relation->isMultiple()) {
            unset($this->related[$relationName]);
        } elseif (isset($this->related[$relationName]) && is_array($this->related[$relationName])) {
            /** @psalm-var array<array-key, ActiveRecordInterface> $related */
            $related = $this->related[$relationName];
            foreach ($related as $a => $b) {
                if ($linkedModel->primaryKeyValues() === $b->primaryKeyValues()) {
                    unset($this->related[$relationName][$a]);
                }
            }
        }
    }

    public function unlinkAll(string $relationName, bool $delete = false): void
    {
        $viaModel = null;
        $viaTable = null;
        $relation = $this->relationQuery($relationName);
        $viaRelation = $relation->getVia();

        if ($viaRelation !== null) {
            if (is_array($viaRelation)) {
                [$viaName, $viaRelation] = $viaRelation;
                $viaModel = $viaRelation->getModel();
                unset($this->related[$viaName]);
            } else {
                $from = $viaRelation->getFrom();
                $viaTable = reset($from);
            }

            $condition = [];
            $nulls = [];

            if ($viaRelation instanceof ActiveQueryInterface) {
                foreach ($viaRelation->getLink() as $a => $b) {
                    $nulls[$a] = null;
                    $condition[$a] = $this->get($b);
                }

                if (!empty($viaRelation->getWhere())) {
                    $condition = ['and', $condition, $viaRelation->getWhere()];
                }

                if (!empty($viaRelation->getOn())) {
                    $condition = ['and', $condition, $viaRelation->getOn()];
                }
            }

            if ($viaModel !== null) {
                if ($delete) {
                    $viaModel->deleteAll($condition);
                } else {
                    $viaModel->updateAll($nulls, $condition);
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
            $relatedModel = $relation->getModel();

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

    public function tableName(): string
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
     * @param array|null $properties List of property names or name-values pairs that need to be saved.
     * Defaults to `null`, meaning all changed property values will be saved.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws Throwable
     */
    abstract protected function insertInternal(?array $properties = null): void;

    /**
     * Sets the value of the named property.
     *
     * @param string $name The property name.
     * @param mixed $value The property value.
     */
    abstract protected function populateProperty(string $name, mixed $value): void;

    /**
     * Internal method to insert or update a record in the database.
     *
     * @see upsert()
     */
    abstract protected function upsertInternal(
        ?array $insertProperties = null,
        array|bool $updateProperties = true,
    ): void;

    protected function retrieveRelation(string $name): ActiveRecordInterface|array|null
    {
        /** @var ActiveQueryInterface $query */
        $query = $this->relationQuery($name);

        $this->setRelationDependencies($name, $query);

        return $this->related[$name] = $query->relatedRecords();
    }

    /**
     * Creates a query instance for `has-one` or `has-many` relation.
     *
     * @param ActiveRecordInterface|string $modelClass The class name of the related record.
     * @param array $link The primary-foreign key constraint.
     * @param bool $multiple Whether this query represents a relation to more than one record.
     *
     * @return ActiveQueryInterface The relational query object.
     *
     * @psalm-param ModelClass $modelClass
     * @psalm-param array<string, string> $link
     *
     * {@see hasOne()}
     * {@see hasMany()}
     */
    protected function createRelationQuery(
        ActiveRecordInterface|string $modelClass,
        array $link,
        bool $multiple,
    ): ActiveQueryInterface {
        return $this->createQuery($modelClass)->primaryModel($this)->link($link)->multiple($multiple);
    }

    /**
     * {@see delete()}
     *
     * @throws Exception
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
        $condition = $this->primaryKeyOldValues();

        if ($this instanceof OptimisticLockInterface) {
            $lock = $this->optimisticLockPropertyName();
            $condition[$lock] = $this->get($lock);

            $result = $this->deleteAll($condition);

            if ($result === 0) {
                throw new OptimisticLockException(
                    'The object being deleted is outdated.',
                );
            }
        } else {
            $result = $this->deleteAll($condition);
        }

        $this->assignOldValues();

        return $result;
    }

    /**
     * Returns the property values that have been modified.
     * You may specify the properties to be returned as list of name or name-value pairs.
     * If name-value pair specified, the corresponding property values will be modified.
     *
     * Only the {@see newValues() changed property values} will be returned.
     *
     * @param array|null $properties List of property names or name-values pairs that need to be returned.
     * Defaults to `null`, meaning all changed property values will be returned.
     *
     * @return array The changed property values (name-value pairs).
     *
     * @psalm-return array<string, mixed>
     */
    protected function newPropertyValues(?array $properties = null): array
    {
        if (empty($properties) || array_is_list($properties)) {
            return $this->newValues($properties);
        }

        $names = [];

        foreach ($properties as $name => $value) {
            if (is_int($name)) {
                $names[] = $value;
            } else {
                $this->set($name, $value);
                $names[] = $name;
            }
        }

        return $this->newValues($names);
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
     * @param array|null $properties Property names or name-values pairs to update, `null` means all properties.
     *
     * @throws Exception
     * @throws NotSupportedException
     *
     * @return int The number of rows affected.
     */
    protected function updateInternal(?array $properties = null): int
    {
        if ($this->isNew()) {
            throw new InvalidCallException('The record is new and cannot be updated.');
        }

        $values = $this->newPropertyValues($properties);
        if (empty($values)) {
            return 0;
        }

        $condition = $this->primaryKeyOldValues();

        if ($this instanceof OptimisticLockInterface) {
            $lock = $this->optimisticLockPropertyName();

            /**
             * @var int $lockValue We assume that optimistic lock property value is always an integer.
             */
            $lockValue = $this->get($lock);

            $condition[$lock] = $lockValue;
            $values[$lock] = ++$lockValue;

            $rows = $this->updateAll($values, $condition);

            if ($rows === 0) {
                throw new OptimisticLockException(
                    'The object being updated is outdated.',
                );
            }

            $this->populateProperty($lock, $lockValue);
        } else {
            $rows = $this->updateAll($values, $condition);
        }

        $this->oldValues = array_merge($this->oldValues ?? [], $values);

        return $rows;
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
        ?string $viaRelationName = null,
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
            [$viaRelationName, $viaQuery] = $via;
            $this->setRelationDependencies($name, $viaQuery, $viaRelationName);
        }
    }

    /**
     * @psalm-param array<string, string> $link
     */
    private function bindModels(
        array $link,
        ActiveRecordInterface $foreignModel,
        ActiveRecordInterface $primaryModel,
    ): void {
        foreach ($link as $fk => $pk) {
            $value = $primaryModel->get($pk);

            if ($value === null) {
                throw new InvalidCallException(
                    'Unable to link active record: the primary key of ' . $primaryModel::class . ' is null.',
                );
            }

            // Relation via array valued property
            if (is_array($fkValue = $foreignModel->get($fk))) {
                $fkValue[] = $value;
                $foreignModel->set($fk, $fkValue);
            } else {
                $foreignModel->set($fk, $value);
            }
        }

        $foreignModel->save();
    }
}
