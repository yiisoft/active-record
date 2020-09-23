<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Redis;

use JsonException;
use Yiisoft\ActiveRecord\BaseActiveRecord;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Strings\Inflector;
use Yiisoft\Strings\StringHelper;

use function count;
use function ctype_alnum;
use function end;
use function implode;
use function is_array;
use function is_numeric;
use function is_string;
use function json_encode;
use function ksort;
use function md5;
use function reset;

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
     * @return ActiveQuery the newly created {@see ActiveQuery} instance.
     */
    public function find(): ActiveQuery
    {
        return new ActiveQuery(static::class, $this->getDb());
    }

    /**
     * Returns the primary key name(s) for this AR class.
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
     * For example, 'Customer' becomes 'customer', and 'OrderItem' becomes 'order_item'. You may override this method
     * if you want different key naming.
     *
     * @return string the prefix to apply to all AR keys
     */
    public static function keyPrefix(): string
    {
        return (new Inflector())->pascalCaseToId(StringHelper::basename(static::class), '_');
    }

    /**
     * Inserts the record into the database using the attribute values of this record.
     *
     * Usage example:
     *
     * ```php
     * $customer = new Customer();
     * $customer->setAttribute('name', $name);
     * $customer->setAttribute('email', $email);
     * $customer->insert();
     * ```
     *
     * @param array|null $attributes list of attributes that need to be saved. Defaults to `null`, meaning all attributes
     * that are loaded from DB will be saved.
     *
     * @throws JsonException|InvalidArgumentException
     *
     * @return bool whether the attributes are valid and the record is inserted successfully.
     */
    public function insert(?array $attributes = null): bool
    {
        $db = $this->getDb();

        $values = $this->getDirtyAttributes($attributes);

        $pk = [];

        foreach ($this->primaryKey() as $key) {
            $pk[$key] = $values[$key] = $this->getAttribute($key);
            if ($pk[$key] === null) {
                /** use auto increment if pk is null */
                $pk[$key] = $values[$key] = $db->executeCommand(
                    'INCR',
                    [static::keyPrefix() . ':s:' . $key]
                );

                $this->setAttribute($key, $values[$key]);
            } elseif (is_numeric($pk[$key])) {
                /** if pk is numeric update auto increment value */
                $currentPk = $db->executeCommand('GET', [static::keyPrefix() . ':s:' . $key]);

                if ($pk[$key] > $currentPk) {
                    $db->executeCommand('SET', [static::keyPrefix() . ':s:' . $key, $pk[$key]]);
                }
            }
        }

        /** save pk in a `findAll()` pool */
        $pk = static::buildKey($pk);

        $db->executeCommand('RPUSH', [static::keyPrefix(), $pk]);

        $key = static::keyPrefix() . ':a:' . $pk;

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
     * Customer::updateAll(['status' => 1], ['id' => 2]);
     * ```
     *
     * @param array $attributes attribute values (name-value pairs) to be saved into the table.
     * @param array|string $condition the conditions that will be put in the WHERE part of the UPDATE SQL.
     * Please refer to {@see ActiveQuery::where()} on how to specify this parameter.
     * @param array $params
     *
     * @throws JsonException
     *
     * @return int the number of rows updated.
     */
    public static function updateAll(array $attributes, $condition = [], array $params = []): int
    {
        $db = static::getConnection();

        if (empty($attributes)) {
            return 0;
        }

        $n = 0;

        foreach (self::fetchPks($condition) as $pk) {
            $newPk = $pk;
            $pk = static::buildKey($pk);
            $key = static::keyPrefix() . ':a:' . $pk;

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

            $newPk = static::buildKey($newPk);
            $newKey = static::keyPrefix() . ':a:' . $newPk;

            /** rename index if pk changed */
            if ($newPk !== $pk) {
                $db->executeCommand('MULTI');

                if (count($setArgs) > 1) {
                    $db->executeCommand('HMSET', $setArgs);
                }

                if (count($delArgs) > 1) {
                    $db->executeCommand('HDEL', $delArgs);
                }

                $db->executeCommand('LINSERT', [static::keyPrefix(), 'AFTER', $pk, $newPk]);
                $db->executeCommand('LREM', [static::keyPrefix(), 0, $pk]);
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
     * Customer::updateAllCounters(['age' => 1]);
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
    public static function updateAllCounters(array $counters, $condition = '', array $params = []): int
    {
        if (empty($counters)) {
            return 0;
        }

        $n = 0;

        foreach (self::fetchPks($condition) as $pk) {
            $key = static::keyPrefix() . ':a:' . static::buildKey($pk);
            foreach ($counters as $attribute => $value) {
                static::getConnection()->executeCommand('HINCRBY', [$key, $attribute, $value]);
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
     * Customer::deleteAll(['status' => 3]);
     * ```
     *
     * @param array|null $condition the conditions that will be put in the WHERE part of the DELETE SQL.
     * @param array $params
     *
     * @throws JsonException
     *
     * @return int the number of rows deleted.
     *
     * Please refer to {@see ActiveQuery::where()} on how to specify this parameter.
     */
    public static function deleteAll(?array $condition = null, array $params = []): int
    {
        $db = static::getConnection();

        $pks = self::fetchPks($condition);

        if (empty($pks)) {
            return 0;
        }

        $attributeKeys = [];

        $db->executeCommand('MULTI');

        foreach ($pks as $pk) {
            $pk = static::buildKey($pk);
            $db->executeCommand('LREM', [static::keyPrefix(), 0, $pk]);
            $attributeKeys[] = static::keyPrefix() . ':a:' . $pk;
        }

        $db->executeCommand('DEL', $attributeKeys);

        $result = $db->executeCommand('EXEC');

        return (int) end($result);
    }

    private static function fetchPks(?array $condition = []): array
    {
        $query = static::find();

        $query->where($condition);

        /** limit fetched columns to pk */
        $records = $query->asArray()->all();

        $primaryKey = static::primaryKey();

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
     * @return string|int the generated key.
     */
    public static function buildKey($key)
    {
        if (is_numeric($key)) {
            return $key;
        }

        if (is_string($key)) {
            return ctype_alnum($key) && StringHelper::byteLength($key) <= 32 ? $key : md5($key);
        }

        if (is_array($key)) {
            if (count($key) === 1) {
                return self::buildKey(reset($key));
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
