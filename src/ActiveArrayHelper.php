<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use function array_key_exists;

final class ActiveArrayHelper
{
    public static function set(array &$array, string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $data = &$array;

        foreach ($keys as $k) {
            if (!self::has($array, $k)) {
                $data[$k] = [];
            }

            $data = &$data[$k];
        }

        $data = $value;
    }

    public static function remove(array &$array, string $key): void
    {
        $keys = explode('.', $key);
        $data = &$array;
        $lastKey = array_pop($keys);

        foreach ($keys as $k) {
            if (!self::has($array, $k)) {
                return;
            }

            $data = &$data[$k];
        }

        unset($data[$lastKey]);
    }

    private static function has(array $array, string $key): bool
    {
        return array_key_exists($key, $array);
    }
}
