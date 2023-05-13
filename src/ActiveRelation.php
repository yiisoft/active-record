<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Yiisoft\Db\Helper\DbArrayHelper;

use function array_key_exists;

final class ActiveRelation
{
    public function __construct(public array $data = [])
    {
    }

    public function add(string $key, mixed $value): void
    {
        $this->data[$key][] = $value;
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function get(string $key): mixed
    {
        return DbArrayHelper::getValueByPath($this->data, $key);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function keys(): array
    {
        return array_keys($this->data);
    }

    public function set(string $key, mixed $value): void
    {
        ActiveArrayHelper::set($this->data, $key, $value);
    }

    public function remove(string $key): void
    {
        ActiveArrayHelper::remove($this->data, $key);
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
