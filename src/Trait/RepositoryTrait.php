<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\Db\Expression\ExpressionInterface;

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
     * Returns an instance of {@see ActiveQueryInterface} instantiated by {@see ActiveRecordInterface::query()} method.
     * If the `$condition` parameter is not null, it calls {@see ActiveQueryInterface::andWhere()} method.
     */
    public static function find(array|string|ExpressionInterface|null $condition = null, array $params = []): ActiveQueryInterface
    {
        $query = static::instantiate()->query();

        if ($condition === null) {
            return $query;
        }

        return $query->andWhere($condition, $params);
    }

    /**
     * Shortcut for {@see find()} method with calling {@see ActiveQueryInterface::all()} method to get all records.
     *
     * @return (ActiveRecordInterface|array)[]
     */
    public static function findAll(array|string|ExpressionInterface|null $condition = null, array $params = []): array
    {
        return static::find($condition, $params)->all();
    }

    /**
     * Shortcut for {@see ActiveQueryInterface::findByPk()} method.
     */
    public static function findByPk(array|float|int|string $values): array|ActiveRecordInterface|null
    {
        return static::instantiate()->query()->findByPk($values);
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
    public static function findOne(
        array|string|ExpressionInterface|null $condition = null,
        array $params = [],
    ): ActiveRecordInterface|array|null {
        return static::find($condition, $params)->one();
    }

    protected static function instantiate(): ActiveRecordInterface
    {
        return new static();
    }
}
