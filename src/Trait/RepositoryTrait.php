<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\Db\Expression\ExpressionInterface;
use Yiisoft\ActiveRecord\NotFoundException;

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
 * $user = User::findByPk(1);
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
     * Do not to pass user input to this method, use {@see findByPk()} instead.
     *
     * @param array|ExpressionInterface|string|null $condition The condition to be applied to the query where clause.
     * No condition is applied if `null` (by default).
     * @param array $params The parameters to be bound to the SQL statement during execution.
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
     * Do not to pass user input to this method, use {@see findByPk()} instead.
     *
     * ```php
     * // find the customers whose primary key value is 10, 11 or 12.
     * $customers = Customer::findAll(['id' => [10, 11, 12]]);
     * // the above code line is equal to:
     * $customers = Customer::find(['id' => [10, 11, 12]])->all();
     *
     * // find customers whose age is 30 and whose status is 1.
     * $customers = Customer::findAll(['age' => 30, 'status' => 1]);
     * // the above code line is equal to.
     * $customers = Customer::find(['age' => 30, 'status' => 1])->all();
     * ```
     *
     * > [!WARNING]
     * > Do NOT use the following code! It is possible to inject any condition to filter by arbitrary column values!
     *
     * ```php
     * $id = $request->getAttribute('id');
     * $posts = Post::findAll($id); // Do NOT use this!
     * ```
     *
     * Explicitly specifying the column to search:
     *
     * ```php
     * $posts = Post::findAll(['id' => $id]);
     * // or use {@see findByPk()} method
     * $post = Post::findByPk($id);
     * ```
     *
     * @param array|ExpressionInterface|string|null $condition The condition to be applied to the query where clause.
     * Returns all records if `null` (by default).
     * @param array $params The parameters to be bound to the SQL statement during execution.
     *
     * @return ActiveRecordInterface[]|array[] An array of ActiveRecord instance, or an empty array if nothing matches.
     */
    public static function findAll(array|string|ExpressionInterface|null $condition = null, array $params = []): array
    {
        return static::find($condition, $params)->all();
    }

    /**
     * Shortcut for {@see findAll()} method with throwing {@see NotFoundException} if no records found.
     *
     * ```php
     * $customers = Customer::findAllOrFail(['is_active' => true]);
     * ```
     *
     * @param array|ExpressionInterface|string|null $condition The condition to be applied to the query where clause.
     * Returns all records if `null` (by default).
     * @param array $params The parameters to be bound to the SQL statement during execution.
     *
     * @throws NotFoundException
     *
     * @return ActiveRecordInterface[]|array[] An array of ActiveRecord instance, or throws {@see NotFoundException}
     * if nothing matches.
     */
    public static function findAllOrFail(array|string|ExpressionInterface|null $condition = null, array $params = []): array
    {
        return static::findAll($condition, $params) ?: throw new NotFoundException('No records found.');
    }

    /**
     * Finds an ActiveRecord instance by the given primary key value.
     * In the examples below, the `id` column is the primary key of the table.
     *
     * ```php
     * $customer = Customer::findByPk(1); // WHERE id = 1
     * ```
     *
     * ```php
     * $customer = Customer::findByPk([1]); // WHERE id = 1
     * ```
     *
     * In the examples below, the `id` and `id2` columns are the composite primary key of the table.
     *
     * ```php
     * $orderItem = OrderItem::findByPk([1, 2]); // WHERE id = 1 AND id2 = 2
     * ```
     *
     * If you need to pass user input to this method, make sure the input value is scalar or in case of array, make sure
     * the array values are scalar:
     *
     * ```php
     * public function actionView(ServerRequestInterface $request)
     * {
     *     $id = (string) $request->getAttribute('id');
     *
     *     $customer = Customer::findByPk($id);
     * }
     * ```
     *
     * @param array|float|int|string $values The primary key value(s) to find the record.
     *
     * @return ActiveRecordInterface|array|null Instance matching the primary key value(s), or `null` if nothing matches.
     */
    public static function findByPk(array|float|int|string $values): array|ActiveRecordInterface|null
    {
        return static::instantiate()->query()->findByPk($values);
    }

    /**
     * Shortcut for {@see findByPk()} method with throwing {@see NotFoundException} if no records found.
     *
     * ```php
     * $customer = Customer::findByPkOrFail(1);
     * ```
     *
     * @param array|float|int|string $values The primary key value(s) to find the record.
     *
     * @throws NotFoundException
     *
     * @return ActiveRecordInterface|array|null Instance matching the primary key value(s),
     * or throws {@see NotFoundException} if nothing matches.
     */
    public static function findByPkOrFail(array|float|int|string $values): array|ActiveRecordInterface|null
    {
        return static::findByPk($values) ?? throw new NotFoundException('No records found.');
    }

    /**
     * Creates an {@see ActiveQueryInterface} instance with a given SQL statement.
     *
     * Note: That because the SQL statement is already specified, calling more query modification methods
     * (such as {@see where()}, {@see order()) on the created {@see ActiveQueryInterface} instance will have no effect.
     *
     * However, calling {@see with()}, {@see asArray()}, {@see indexBy()} or {@see resultCallback()} is still fine.
     *
     * Below is an example:
     *
     * ```php
     * $customers = Customer::findBySql('SELECT * FROM customer')->all();
     * ```
     *
     * @param string $sql The SQL statement to be executed.
     * @param array $params The parameters to be bound to the SQL statement during execution.
     *
     * @return ActiveQueryInterface The newly created {@see ActiveQueryInterface} instance.
     */
    public static function findBySql(string $sql, array $params = []): ActiveQueryInterface
    {
        return static::instantiate()->query()->sql($sql)->params($params);
    }

    /**
     * Shortcut for {@see find()} method with calling {@see ActiveQueryInterface::one()} method to get one record.
     * Do not to pass user input to this method, use {@see findByPk()} instead.
     *
     * ```php
     * // find a single customer whose primary key value is 10
     * $customer = Customer::findOne(['id' => 10]);
     * // the above code line is equal to:
     * $customer = Customer::find(['id' => 10])->one();
     *
     * // find the first customer whose age is 30 and whose status is 1
     * $customer = Customer::findOne(['age' => 30, 'status' => 1]);
     * // the above code line is equal to:
     * $customer = Customer::find(['age' => 30, 'status' => 1])->one();
     * ```
     *
     * > [!WARNING]
     * > Do NOT use the following code! It is possible to inject any condition to filter by arbitrary column values!
     *
     * ```php
     * $id = $request->getAttribute('id');
     * $post = Post::findOne($id); // Do NOT use this!
     * ```
     *
     * Explicitly specifying the column to search:
     *
     * ```php
     * $post = Post::findOne(['id' => $id]);
     * // or use {@see findByPk()} method
     * $post = Post::findByPk($id);
     * ```
     *
     * @param array|ExpressionInterface|string|null $condition The condition to be applied to the query where clause.
     * Returns the first record if `null` (by default).
     * @param array $params The parameters to be bound to the SQL statement during execution.
     *
     * @return ActiveRecordInterface|array|null Instance matching the condition, or `null` if nothing matches.
     */
    public static function findOne(
        array|string|ExpressionInterface|null $condition = null,
        array $params = [],
    ): ActiveRecordInterface|array|null {
        return static::find($condition, $params)->one();
    }

    /**
     * Shortcut for {@see findOne()} method with throwing {@see NotFoundException} if no records found.
     *
     * ```php
     * $customer = Customer::findOneOrFail(['id' => 1]);
     * ```
     *
     * @param array|ExpressionInterface|string|null $condition The condition to be applied to the query where clause.
     * Returns the first record if `null` (by default).
     * @param array $params The parameters to be bound to the SQL statement during execution.
     *
     * @throws NotFoundException
     *
     * @return ActiveRecordInterface|array|null Instance matching the condition, or throws {@see NotFoundException}
     * if nothing matches.
     */
    public static function findOneOrFail(
        array|string|ExpressionInterface|null $condition = null,
        array $params = [],
    ): ActiveRecordInterface|array|null {
        return static::findOne($condition, $params) ?? throw new NotFoundException('No records found.');
    }

    protected static function instantiate(): ActiveRecordInterface
    {
        return new static();
    }
}
