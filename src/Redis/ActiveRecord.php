<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Redis;

use function count;
use function ctype_alnum;
use function end;
use function implode;
use function is_array;
use function is_bool;

use function is_numeric;
use function is_string;
use function json_encode;
use JsonException;
use function ksort;
use function md5;
use function reset;
use Yiisoft\ActiveRecord\BaseActiveRecord;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Strings\Inflector;
use Yiisoft\Strings\StringHelper;

/**
 * ActiveRecord is the base class for classes representing relational data in terms of objects.
 *
 * This class implements the ActiveRecord pattern for the [redis](http://redis.io/) key-value store.
 *
 * For defining a record a subclass should at least implement the {@see attributes()} method to define attributes. A
 * primary key can be defined via {@see primaryKey()} which defaults to `id` if not specified.
 *
 * The following is an example model called `Customer`:
 *
 * ```php
 * class Customer extends \Yiisoft\Db\Redis\ActiveRecord
 * {
 *     public function attributes()
 *     {
 *         return ['id', 'name', 'address', 'registration_date'];
 *     }
 * }
 * ```
 */
class ActiveRecord extends BaseActiveRecord
{
    /**
     * Returns the primary key name(s) for this active record class.
     *
     * This method should be overridden by child classes to define the primary key. Note that an array should be
     * returned even when it is a single primary key.
     *
     * @return array the primary keys of this record.
     */
    public function primaryKey(): array
    {
        return ['id'];
    }

    /**
     * Returns the list of all attribute names of the model.
     *
     * This method must be overridden by child classes to define available attributes.
     *
     * @throws InvalidConfigException
     *
     * @return array list of attribute names.
     */
    public function attributes(): array
    {
        throw new InvalidConfigException(
            'The attributes() method of redis ActiveRecord has to be implemented by child classes.'
        );
    }

    /**
     * Declares prefix of the key that represents the keys that store this records in redis.
     *
     * By default this method returns the class name as the table name by calling {@see Inflector->pascalCaseToId()}.
     * For example, 'Customer' becomes 'customer', and 'OrderItem' becomes 'order_item'. You may override this method if
     * you want different key naming.
     *
     * @return string the prefix to apply to all AR keys.
     */
    public function keyPrefix(): string
    {
        $inflector = new Inflector();

        return $inflector->pascalCaseToId(StringHelper::basename(static::class), '_');
    }

    /**
     * Inserts the record into the database using the attribute values of this record.
     *
     * Usage example:
     *
     * ```php
     * $customer = new Customer($db);
     * $customer->setAttribute('name', $name);
     * $customer->setAttribute('email', $email);
     * $customer->insert();
     * ```
     *
     * @param array|null $attributes list of attributes that need to be saved. Defaults to `null`, meaning all
     * attributes that are loaded from DB will be saved.
     *
     * @throws InvalidArgumentException|JsonException
     *
     * @return bool whether the attributes are valid and the record is inserted successfully.
     */
    public function insert(array $attributes = null): bool
    {
        $db = $this->db;

        $values = $this->getDirtyAttributes($attributes);

        $pk = [];

        foreach ($this->primaryKey() as $key) {
            $pk[$key] = $values[$key] = $this->getAttribute($key);
            if ($pk[$key] === null) {
                /** use auto increment if pk is null */
                $pk[$key] = $values[$key] = $db->executeCommand(
                    'INCR',
                    [$this->keyPrefix() . ':s:' . $key]
                );

                $this->setAttribute($key, $values[$key]);
            } elseif (is_numeric($pk[$key])) {
                /** if pk is numeric update auto increment value */
                $currentPk = $db->executeCommand('GET', [$this->keyPrefix() . ':s:' . $key]);

                if ($pk[$key] > $currentPk) {
                    $db->executeCommand('SET', [$this->keyPrefix() . ':s:' . $key, $pk[$key]]);
                }
            }
        }

        /** save pk in a `findAll()` pool */
        $pk = $this->buildKey($pk);

        $db->executeCommand('RPUSH', [$this->keyPrefix(), $pk]);

        $key = $this->keyPrefix() . ':a:' . $pk;

        /** save attributes */
        $setArgs = [$key];

        foreach ($values as $attribute => $value) {
            /** only insert attributes that are not null */
            if ($value !== null) {
                if (is_bool($value)) {
                    $value = (int) $value;
                }
                $setArgs[] = $attribute;
                $setArgs[] = $value;
            }
        }

        if (count($setArgs) > 1) {
            $db->executeCommand('HMSET', $setArgs);
        }

        $this->setOldAttributes($values);

        return true;
    }

    /**
     * Updates the whole table using the provided attribute values and conditions.
     *
     * For example, to change the status to be 1 for all customers whose status is 2:
     *
     * ```php
     * $customer = new Customer($db);
     * $customer->updateAll(['status' => 1], ['id' => 2]);
     * ```
     *
     * @param array $attributes attribute values (name-value pairs) to be saved into the table.
     * @param array|string|null $condition the conditions that will be put in the WHERE part of the UPDATE SQL.
     * Please refer to {@see ActiveQuery::where()} on how to specify this parameter.
     * @param array $params
     *
     * @throws JsonException
     *
     * @return int the number of rows updated.
     */
    public function updateAll(array $attributes, $condition = null, array $params = []): int
    {
        $db = $this->db;

        if (empty($attributes)) {
            return 0;
        }

        $n = 0;

        foreach ($this->fetchPks($condition) as $pk) {
            $newPk = $pk;
            $pk = $this->buildKey($pk);
            $key = $this->keyPrefix() . ':a:' . $pk;

            /** save attributes */
            $delArgs = [$key];
            $setArgs = [$key];

            foreach ($attributes as $attribute => $value) {
                if (isset($newPk[$attribute])) {
                    $newPk[$attribute] = $value;
                }

                if ($value !== null) {
                    if (is_bool($value)) {
                        $value = (int) $value;
                    }
                    $setArgs[] = $attribute;
                    $setArgs[] = $value;
                } else {
                    $delArgs[] = $attribute;
                }
            }

            $newPk = $this->buildKey($newPk);
            $newKey = $this->keyPrefix() . ':a:' . $newPk;

            /** rename index if pk changed */
            if ($newPk !== $pk) {
                $db->executeCommand('MULTI');

                if (count($setArgs) > 1) {
                    $db->executeCommand('HMSET', $setArgs);
                }

                if (count($delArgs) > 1) {
                    $db->executeCommand('HDEL', $delArgs);
                }

                $db->executeCommand('LINSERT', [$this->keyPrefix(), 'AFTER', $pk, $newPk]);
                $db->executeCommand('LREM', [$this->keyPrefix(), 0, $pk]);
                $db->executeCommand('RENAME', [$key, $newKey]);
                $db->executeCommand('EXEC');
            } else {
                if (count($setArgs) > 1) {
                    $db->executeCommand('HMSET', $setArgs);
                }

                if (count($delArgs) > 1) {
                    $db->executeCommand('HDEL', $delArgs);
                }
            }

            $n++;
        }

        return $n;
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
     * @param array $counters the counters to be updated (attribute name => increment value). Use negative values if you
     * want to decrement the counters.
     * @param array|string $condition the conditions that will be put in the WHERE part of the UPDATE SQL.
     * @param array $params
     *
     * @throws JsonException
     *
     * @return int the number of rows updated.
     *
     * Please refer to {@see ActiveQuery::where()} on how to specify this parameter.
     */
    public function updateAllCounters(array $counters, $condition = [], array $params = []): int
    {
        if (empty($counters)) {
            return 0;
        }

        $n = 0;

        foreach ($this->fetchPks($condition) as $pk) {
            $key = $this->keyPrefix() . ':a:' . $this->buildKey($pk);
            foreach ($counters as $attribute => $value) {
                $this->db->executeCommand('HINCRBY', [$key, $attribute, $value]);
            }
            $n++;
        }

        return $n;
    }

    /**
     * Deletes rows in the table using the provided conditions.
     *
     * WARNING: If you do not specify any condition, this method will delete ALL rows in the table.
     *
     * For example, to delete all customers whose status is 3:
     *
     * ```php
     * $customer = new Customer($db);
     * $customer->deleteAll(['status' => 3]);
     * ```
     *
     * @param array|null $condition the conditions that will be put in the WHERE part of the DELETE SQL.
     *
     * @throws JsonException
     *
     * @return int the number of rows deleted.
     *
     * Please refer to {@see ActiveQuery::where()} on how to specify this parameter.
     */
    public function deleteAll(array $condition = null): int
    {
        $db = $this->db;

        $pks = $this->fetchPks($condition);

        if (empty($pks)) {
            return 0;
        }

        $attributeKeys = [];

        $db->executeCommand('MULTI');

        foreach ($pks as $pk) {
            $pk = $this->buildKey($pk);
            $db->executeCommand('LREM', [$this->keyPrefix(), 0, $pk]);
            $attributeKeys[] = $this->keyPrefix() . ':a:' . $pk;
        }

        $db->executeCommand('DEL', $attributeKeys);

        $result = $db->executeCommand('EXEC');

        return (int) end($result);
    }

    /**
     * @param array|string|null $condition
     */
    private function fetchPks($condition = []): array
    {
        $query = $this->instantiateQuery(static::class);

        $query->where($condition);

        /** limit fetched columns to pk */
        $records = $query->asArray()->all();

        $primaryKey = $this->primaryKey();

        $pks = [];

        foreach ($records as $record) {
            $pk = [];

            foreach ($primaryKey as $key) {
                $pk[$key] = $record[$key];
            }

            $pks[] = $pk;
        }

        return $pks;
    }

    /**
     * Builds a normalized key from a given primary key value.
     *
     * @param mixed $key the key to be normalized.
     *
     * @throws JsonException
     *
     * @return int|string the generated key.
     */
    public function buildKey($key)
    {
        if (is_numeric($key)) {
            return $key;
        }

        if (is_string($key)) {
            return ctype_alnum($key) && StringHelper::byteLength($key) <= 32 ? $key : md5($key);
        }

        if (is_array($key)) {
            if (count($key) === 1) {
                return $this->buildKey(reset($key));
            }

            /** ensure order is always the same */
            ksort($key);

            $isNumeric = true;

            foreach ($key as $value) {
                if (!is_numeric($value)) {
                    $isNumeric = false;
                }
            }

            if ($isNumeric) {
                return implode('-', $key);
            }
        }

        return md5(json_encode($key, JSON_THROW_ON_ERROR | JSON_NUMERIC_CHECK));
    }
}
