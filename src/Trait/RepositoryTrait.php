<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use Closure;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * Trait to support static methods {@see find()}, {@see findOne()}, {@see findAll()}, {@see findBySql()} to find records.
 *
 * @method ActiveQueryInterface query(ActiveRecordInterface|Closure|null|string $arClass = null)
 */
trait RepositoryTrait
{
    /**
     * Shortcut for {@see ActiveQueryInterface::find()} method.
     */
    public static function find(array|float|int|string $properties = []): ActiveQueryInterface
    {
        $query = static::instantiate()->query();

        if ($properties === []) {
            return $query;
        }

        return $query->find($properties);
    }

    /**
     * Shortcut for {@see find()} method with calling {@see ActiveQueryInterface::all()} method to get all records.
     *
     * @return (ActiveRecordInterface|array)[]
     */
    public static function findAll(array|float|int|string $properties = []): array
    {
        return static::find($properties)->all();
    }

    /**
     * Shortcut for {@see ActiveQueryInterface::findBySql()} method.
     */
    public static function findBySql(string $sql, array $params = []): ActiveQueryInterface
    {
        return static::instantiate()->query()->findBySql($sql, $params);
    }

    /**
     * Shortcut for {@see find()} method with calling {@see ActiveQueryInterface::one()} method to get one record.
     */
    public static function findOne(array|float|int|string $properties = []): ActiveRecordInterface|array|null
    {
        return static::find($properties)->one();
    }

    protected static function instantiate(): ActiveRecordInterface
    {
        return new static();
    }
}
