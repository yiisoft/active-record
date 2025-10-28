<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Internal;

use Closure;
use RuntimeException;
use Traversable;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\Db\Query\QueryInterface;

use function array_combine;
use function array_key_exists;
use function array_map;
use function get_object_vars;
use function is_array;
use function iterator_to_array;
use function property_exists;
use function strrpos;
use function substr;

/**
 * @internal
 *
 * Array manipulation methods for ActiveRecord.
 *
 * @psalm-type Row = ActiveRecordInterface|array
 * @psalm-import-type IndexBy from QueryInterface
 */
final class ArArrayHelper
{
    /**
     * Returns the values of a specified column in an array.
     *
     * The input array should be multidimensional or an array of {@see ActiveRecordInterface} instances.
     *
     * For example,
     *
     * ```php
     * $array = [
     *     ['id' => '123', 'data' => 'abc'],
     *     ['id' => '345', 'data' => 'def'],
     * ];
     * $result = ArArrayHelper::getColumn($array, 'id');
     * // the result is: ['123', '345']
     * ```
     *
     * @param ActiveRecordInterface[]|array[] $array Array to extract values from.
     * @param string $name The column name.
     *
     * @return array The list of column values.
     */
    public static function getColumn(array $array, string $name): array
    {
        return array_map(
            static fn (ActiveRecordInterface|array $element): mixed => self::getValueByPath($element, $name),
            $array
        );
    }

    /**
     * Retrieves a value from the array by the given key or from the {@see ActiveRecordInterface} instance
     * by the given property or relation name.
     *
     * If the key doesn't exist, the default value will be returned instead.
     *
     * The key may be specified in a dot format to retrieve the value of a sub-array or a property or relation of the
     * {@see ActiveRecordInterface} instance.
     *
     * In particular, if the key is `x.y.z`, then the returned value would be `$array['x']['y']['z']` or
     * `$array->x->y->z` (if `$array` is an {@see ActiveRecordInterface} instance).
     *
     * Note that if the array already has an element `x.y.z`, then its value will be returned instead of going through
     * the sub-arrays.
     *
     * Below are some usage examples.
     *
     * ```php
     * // working with an array
     * $username = ArArrayHelper::getValueByPath($array, 'username');
     * // working with an {@see ActiveRecordInterface} instance
     * $username = ArArrayHelper::getValueByPath($user, 'username');
     * // using dot format to retrieve the property of an {@see ActiveRecordInterface} instance
     * $street = ArArrayHelper::getValue($users, 'address.street');
     * ```
     *
     * @param ActiveRecordInterface|array $array Array or an {@see ActiveRecordInterface} instance to extract value from.
     * @param string $key Key name of the array element or a property or relation name
     * of the {@see ActiveRecordInterface} instance.
     * @param mixed|null $default The default value to be returned if the specified `$key` doesn't exist.
     *
     * @return mixed The value of the element if found, default value otherwise
     */
    public static function getValueByPath(ActiveRecordInterface|array $array, string $key, mixed $default = null): mixed
    {
        if ($array instanceof ActiveRecordInterface) {
            if ($array->hasProperty($key)) {
                return $array->get($key);
            }

            if (property_exists($array, $key)) {
                return array_key_exists($key, get_object_vars($array)) ? $array->$key : $default;
            }

            if ($array->isRelationPopulated($key)) {
                return $array->relation($key);
            }
        } elseif (array_key_exists($key, $array)) {
            return $array[$key];
        }

        if (!empty($key) && ($pos = strrpos($key, '.')) !== false) {
            $array = self::getValueByPath($array, substr($key, 0, $pos), $default);
            if (!is_array($array) && !($array instanceof ActiveRecordInterface)) {
                throw new RuntimeException(
                    'Trying to get property of non-array or non-ActiveRecordInterface instance.',
                );
            }
            $key = substr($key, $pos + 1);

            return self::getValueByPath($array, $key, $default);
        }

        return $default;
    }

    /**
     * Indexes an array of rows with the specified column value as keys.
     *
     * The input array should be multidimensional or an array of {@see ActiveRecordInterface} instances.
     *
     * For example,
     *
     * ```php
     * $rows = [
     *     ['id' => '123', 'data' => 'abc'],
     *     ['id' => '345', 'data' => 'def'],
     * ];
     * $result = ArArrayHelper::populate($rows, 'id');
     * // the result is: ['123' => ['id' => '123', 'data' => 'abc'], '345' => ['id' => '345', 'data' => 'def']]
     * ```
     *
     * @param ActiveRecordInterface[]|array[] $rows Array to populate.
     * @param Closure|string|null $indexBy The column name or anonymous function that specifies the index by which to
     * populate the array of rows.
     *
     * @return ActiveRecordInterface[]|array[]
     *
     * @psalm-template TRow of Row
     * @psalm-param array<TRow> $rows
     * @psalm-param IndexBy|null $indexBy
     * @psalm-return ($rows is non-empty-array<TRow> ? non-empty-array<TRow> : array<TRow>)
     */
    public static function index(array $rows, Closure|string|null $indexBy = null): array
    {
        if ($indexBy === null) {
            return $rows;
        }

        if ($indexBy instanceof Closure) {
            return array_combine(array_map($indexBy, $rows), $rows);
        }

        $result = [];

        foreach ($rows as $row) {
            $result[(string) self::getValueByPath($row, $indexBy)] = $row;
        }

        return $result;
    }

    /**
     * Converts an object into an array.
     *
     * @param array|object $object The object to be converted into an array.
     *
     * @return array The array representation of the object.
     *
     * @psalm-param array<string, mixed>|object $object
     * @psalm-return array<string, mixed>
     */
    public static function toArray(array|object $object): array
    {
        if (is_array($object)) {
            return $object;
        }

        if ($object instanceof ActiveRecordInterface) {
            return $object->propertyValues();
        }

        if ($object instanceof Traversable) {
            /**
             * @psalm-var array<string, mixed> We assume that the traversable object yields string keys.
             */
            return iterator_to_array($object);
        }

        return get_object_vars($object);
    }
}
