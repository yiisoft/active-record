<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Closure;

use function array_combine;
use function array_key_exists;
use function array_map;
use function get_object_vars;
use function property_exists;
use function strrpos;
use function substr;

/**
 * Array manipulation methods for ActiveRecord.
 *
 * @psalm-type Row = ActiveRecordInterface|array
 * @psalm-type IndexKey = string|Closure(Row, mixed=):mixed
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
     * @param array $array Array to extract values from.
     * @param string $name The column name.
     *
     * @psalm-param Row[] $array
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
     * @psalm-param Row $array
     *
     * @return mixed The value of the element if found, default value otherwise
     */
    public static function getValueByPath(ActiveRecordInterface|array $array, string $key, mixed $default = null): mixed
    {
        if ($array instanceof ActiveRecordInterface) {
            if ($array->hasAttribute($key)) {
                return $array->getAttribute($key);
            }

            if (property_exists($array, $key)) {
                return get_object_vars($array)[$key] ?? $default;
            }

            if ($array->isRelationPopulated($key)) {
                return $array->relation($key);
            }
        } elseif (array_key_exists($key, $array)) {
            return $array[$key];
        }

        if (!empty($key) && ($pos = strrpos($key, '.')) !== false) {
            $array = self::getValueByPath($array, substr($key, 0, $pos), $default);
            $key = substr($key, $pos + 1);

            return self::getValueByPath($array, $key, $default);
        }

        return $default;
    }

    /**
     * Populates an array of rows with the specified column value as keys.
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
     * @param array[] $rows Array to populate.
     * @param Closure|string|null $indexBy The column name or anonymous function that specifies the index by which to
     * populate the array of rows.
     *
     * @psalm-template TRow of Row
     * @psalm-param array<TRow> $rows
     * @psalm-param IndexKey|null $indexBy
     * @psalm-return array<TRow>
     *
     * @return array[]
     */
    public static function populate(array $rows, Closure|string|null $indexBy = null): array
    {
        if ($indexBy === null) {
            return $rows;
        }

        if ($indexBy instanceof Closure) {
            return array_combine(array_map($indexBy, $rows), $rows);
        }

        $result = [];

        foreach ($rows as $row) {
            /** @psalm-suppress MixedArrayOffset */
            $result[self::getValueByPath($row, $indexBy)] = $row;
        }

        return $result;
    }
}
