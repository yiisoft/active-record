<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * Trait to support static methods {@see find()}, {@see findOne()}, {@see findAll()}, {@see findBySql()} to find records.
 *
 * For example:
 *
 * ```php
 * use Yiisoft\ActiveRecord\ActiveRecord;
 * use Yiisoft\ActiveRecord\Trait\RepositoryTrait;
 *
 * final class User extends ActiveRecord
 * {
 *     use RepositoryTrait;
 *
 *     public int $id;
 *     public bool $is_active;
 * }
 *
 * $user = User::find()->where(['id' => 1])->one();
 * $users = User::find()->where(['is_active' => true])->all();
 *
 * $user = User::findOne(['id' => 1]);
 *
 * $users = User::findAll(['is_active' => true]);
 *
 * $users = User::findBySql('SELECT * FROM customer')->all();
 * ```
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
