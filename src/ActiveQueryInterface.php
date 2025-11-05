<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Closure;
use ReflectionException;
use Throwable;
use Yiisoft\Db\Exception\Exception;
use InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Expression\ExpressionInterface;
use Yiisoft\Db\Query\QueryInterface;
use Yiisoft\Definitions\Exception\CircularReferenceException;
use Yiisoft\Definitions\Exception\NotInstantiableException;

/**
 * A common interface to be implemented by active record query classes.
 *
 * That are methods for all normal queries that return active records but also relational queries in which the query
 * represents a relation between two active record classes and will return related records only.
 *
 * @psalm-type Via = array{string, ActiveQueryInterface, bool}|ActiveQueryInterface
 * @psalm-type ActiveQueryResult = ActiveRecordInterface|array<string, mixed>
 */
interface ActiveQueryInterface extends QueryInterface
{
    /**
     * @inheritdoc
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     *
     * @return ActiveRecordInterface[]|array[] All rows of the query result. Each array element is an `array` or
     * instance of {@see ActiveRecordInterface} representing a row of data, depends on {@see isAsArray()} result.
     * Empty array if the query results in nothing.
     *
     * @psalm-return array<ActiveRecordInterface|array>
     */
    public function all(): array;

    /**
     * Sets the {@see asArray} property.
     *
     * @param bool|null $value Whether to return the query results in terms of arrays instead of Active Records.
     *
     * @return static The query object itself.
     */
    public function asArray(bool|null $value = true): static;

    /**
     * Specifies the relations with which this query should be performed.
     *
     * The parameters to this method can be either one or multiple strings, or a single array of relation names and the
     * optional callbacks to customize the relations.
     *
     * A relation name can refer to a relation defined in {@see modelClass} or a sub-relation that stands for a relation
     * of a related record.
     *
     * For example, `orders.address` means the `address` relation defined in the model class corresponding to the
     * `orders` relation.
     *
     * The following are some usage examples:
     *
     * ```php
     * // find customers together with their orders and country
     * Customer::query()->with('orders', 'country')->all();
     * // find customers together with their orders and the orders' shipping address
     * Customer::query()->with('orders.address')->all();
     * // find customers together with their country and orders of status 1
     * Customer::query()->with([
     *     'orders' => function (ActiveQuery $query) {
     *         $query->andWhere('status = 1');
     *     },
     *     'country',
     * ])->all();
     * ```
     *
     * You can call `with()` multiple times. Each call will add relations to the existing ones.
     *
     * For example, the following two statements are equivalent:
     *
     * ```php
     * Customer::query()->with('orders', 'country')->all();
     * Customer::query()->with('orders')->with('country')->all();
     * ```
     *
     * @param array|string ...$with A list of relation names or relation definitions.
     *
     * @return static The query object itself.
     */
    public function with(array|string ...$with): static;

    /**
     * @return array A list of relations that this query should be performed with.
     */
    public function getWith(): array;

    /**
     * Resets the relations that this query should be performed with.
     *
     * This method clears all relations set via {@see with()} and disables eager loading for relations
     * set via {@see joinWith()}, while keeping the JOIN clauses intact.
     *
     * @return static The query object itself.
     */
    public function resetWith(): static;

    /**
     * Specifies the relation associated with the junction table for use in a relational query.
     *
     * @param string $relationName The relation name.
     * This refers to a relation declared in the
     * {@see ActiveRelationTrait::primaryModel} of the relation.
     * @param callable|null $callable A PHP callback for customizing the relation associated with the junction table.
     * Its signature should be `function($query)`, where `$query` is the query to be customized.
     */
    public function via(string $relationName, callable|null $callable = null): static;

    public function resetVia(): static;

    /**
     * @return array|ExpressionInterface|string|null the join condition to be used when this query is used in a relational context.
     *
     * The condition will be used in the ON part when {@see joinWith()} is called. Otherwise, the condition will be used
     * in the `WHERE` part of a query.
     *
     * Please refer to {@see Query::where()} on how to specify this parameter.
     *
     * @see on()
     */
    public function getOn(): array|ExpressionInterface|string|null;

    /**
     * @return array A list of relations that this query should be joined with.
     * @psalm-return list<JoinWith>
     */
    public function getJoinsWith(): array;

    /**
     * Joins with the specified relations.
     *
     * This method allows you to reuse existing relation definitions to perform `JOIN` queries. Based on the definition of
     * the specified relation(s), the method will append one or many `JOIN` statements to the current query.
     *
     * If the `$eagerLoading` parameter is true, the method will also perform eager loading for the specified relations,
     * which is equal to calling {@see with()} using the specified relations.
     *
     * Note: That because a `JOIN` query will be performed, you're responsible for disambiguated column names.
     *
     * This method differs from {@see with()} in that it will build up and execute a `JOIN` SQL statement for the primary
     * table. And when `$eagerLoading` is true, it will call {@see with()} also with the specified relations.
     *
     * Note: Relations specified in `$with` cannot have `GROUP BY`, `HAVING`, or `UNION` clauses. Using these clauses
     * will result in a {@see \LogicException}.
     *
     * @param array|string $with The relations to be joined. This can either be a string, representing a relation name
     * or an array with the following semantics:
     *
     * - Each array element represents a single relation.
     * - You may specify the relation name as the array key and give anonymous functions that can be used to change the
     *   relation queries on-the-fly as the array value.
     * - If a relation query doesn't need modification, you may use the relation name as the array value.
     *
     * The relation name may optionally contain an alias for the relation table (for example, `books b`).
     *
     * Sub-relations can also be specified, see {@see with()} for the syntax.
     *
     * In the following, you find some examples:
     *
     * ```php
     * // Find all orders that contain books, and eager loading "books".
     * $orderQuery = Order::query();
     * $orderQuery->joinWith('books', true, 'INNER JOIN')->all();
     *
     * // Find all orders, eagerly load "books", and sort the orders and books by the book names.
     * $orderQuery = Order::query();
     * $orderQuery->joinWith([
     *     'books' => function (ActiveQuery $query) {
     *         $query->orderBy('item.name');
     *     }
     * ])->all();
     *
     * // Find all orders that contain books of the category 'Science fiction', using the alias "b" for the book table.
     * $order = Order::query();
     * $orderQuery->joinWith(['books b'], true, 'INNER JOIN')->where(['b.category' => 'Science fiction'])->all();
     * ```
     * @param array|bool $eagerLoading Whether to eager load the relations specified in `$with`. When this is boolean.
     * It applies to all relations specified in `$with`. Use an array to explicitly list which relations in `$with` a
     * need to be eagerly loaded.
     * Note: This doesn't mean that the relations are populated from the query result. An
     * extra query will still be performed to bring in the related data. Defaults to `true`.
     * @param array|string $joinType The join type of the relations specified in `$with`. When this is a string, it
     * applies to all relations specified in `$with`. Use an array in the format of `relationName => joinType` to
     * specify different join types for different relations.
     *
     * @psalm-param array<string|Closure>|string $with
     * @psalm-param array<string,string>|string $joinType
     */
    public function joinWith(
        array|string $with,
        array|bool $eagerLoading = true,
        array|string $joinType = 'LEFT JOIN'
    ): static;

    public function resetJoinsWith(): void;

    /**
     * Inner joins with the specified relations.
     *
     * This is a shortcut method to {@see joinWith()} with the join type set as "INNER JOIN".
     *
     * Please refer to {@see joinWith()} for detailed usage of this method.
     *
     * @param array|string $with The relations to be joined with.
     * @param array|bool $eagerLoading Whether to eager load the relations.
     * Note: That this doesn't mean that the relations are populated from the query result.
     * An extra query will still be performed to bring in the related data.
     *
     * @see joinWith()
     *
     * @psalm-param array<string|Closure>|string $with
     */
    public function innerJoinWith(array|string $with, array|bool $eagerLoading = true): static;

    /**
     * Sets the ON condition for a relational query.
     *
     * The condition will be used in the ON part when {@see joinWith()} is called.
     *
     * Otherwise, the condition will be used in the `WHERE` part of a query.
     *
     * Use this method to specify more conditions when declaring a relation in the {@see ActiveRecord} class:
     *
     * ```php
     * public function getActiveUsers(): ActiveQuery
     * {
     *     return $this->hasMany(User::class, ['id' => 'user_id'])->onCondition(['active' => true]);
     * }
     * ```
     *
     * Note that this condition is applied in case of a join as well as when fetching the related records.
     * These only fields of the related table can be used in the condition.
     * Trying to access fields of the primary record will cause an error in a non-join-query.
     *
     * @param array|ExpressionInterface|string $condition The ON condition. Please refer to {@see Query::where()} on
     * how to specify this parameter.
     * @param array $params The parameters (name => value) to be bound to the query.
     */
    public function on(array|ExpressionInterface|string $condition, array $params = []): static;

    /**
     * Adds ON condition to the existing one.
     *
     * The new condition and the existing one will be joined using the `AND` operator.
     *
     * @param array|ExpressionInterface|string $condition The new `ON` condition. Please refer to {@see where()} on how
     * to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query.
     *
     * @see on()
     * @see orOn()
     */
    public function andOn(array|ExpressionInterface|string $condition, array $params = []): static;

    /**
     * Adds ON condition to the existing one.
     *
     * The new condition and the existing one will be joined using the `OR` operator.
     *
     * @param array|ExpressionInterface|string $condition The new `ON` condition. Please refer to {@see where()} on how
     * to specify this parameter.
     * @param array $params The parameters (name => value) to be bound to the query.
     *
     * @see on()
     * @see andOn()
     */
    public function orOn(array|ExpressionInterface|string $condition, array $params = []): static;

    /**
     * Specifies the junction table for a relational query.
     *
     * Use this method to specify a junction table when declaring a relation in the {@see ActiveRecord} class:
     *
     * ```php
     * public function getItems()
     * {
     *     return $this->hasMany(Item::class, ['id' => 'item_id'])->viaTable('order_item', ['order_id' => 'id']);
     * }
     * ```
     *
     * @param string $tableName The name of the junction table.
     * @param string[] $link The link between the junction table and the table associated with {@see primaryModel}.
     * The keys of the array represent the columns in the junction table, and the values represent the columns in the
     * {@see primaryModel} table.
     * @param callable|null $callable A PHP callback for customizing the relation associated with the junction table.
     * Its signature should be `function($query)`, where `$query` is the query to be customized.
     *
     * @psalm-param array<string,string> $link
     *
     * @see via()
     */
    public function viaTable(string $tableName, array $link, callable|null $callable = null): static;

    /**
     * Define an alias for the table defined in {@see ActiveRecordInterface}.
     *
     * This method will adjust {@see from()} so that an already defined alias will be overwritten.
     *
     * If none was defined, {@see from()} will be populated with the given alias.
     *
     * @param string $alias The table alias.
     *
     * @throws CircularReferenceException
     * @throws NotInstantiableException
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
     */
    public function alias(string $alias): static;

    /**
     * Returns table names used in {@see from} indexed by aliases.
     *
     * Both aliases and names are enclosed into `{{` and `}}`.
     *
     * @throws CircularReferenceException
     * @throws InvalidArgumentException
     * @throws NotInstantiableException
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
     */
    public function getTablesUsedInFrom(): array;

    /**
     * @return string|null The SQL statement to be executed for retrieving AR records.
     *
     * This is set by {@see ActiveRecord::findBySql()}.
     */
    public function getSql(): string|null;

    public function sql(string|null $value): static;

    /**
     * Converts the raw query results into the format as specified by this query.
     *
     * This method is internally used to convert the data fetched from a database into the format as required by this
     * query.
     *
     * @param array[] $rows The raw query result from a database.
     *
     * @return ActiveRecordInterface[]|array[] The converted query result.
     *
     * @psalm-param list<array<string, mixed>> $rows
     * @psalm-return (
     *     $rows is non-empty-list<array<string, mixed>>
     *         ? non-empty-list<ActiveQueryResult>
     *         : list<ActiveQueryResult>
     * )
     */
    public function populate(array $rows): array;

    /**
     * Sets the name of the relation that is the inverse of this relation.
     *
     * For example, a customer has orders, which means the inverse of the "orders" relation is the "customer".
     *
     * If this property is set, the primary record(s) will be referenced through the specified relation.
     *
     * For example, `$customer->orders[0]->customer` and `$customer` will be the same object, and accessing the customer
     * of an order will not trigger a new DB query.
     *
     * Use this method when declaring a relation in the {@see ActiveRecord} class, e.g., in the Customer model:
     *
     * ```php
     * public function getOrdersQuery()
     * {
     *     return $this->hasMany(Order::class, ['customer_id' => 'id'])->inverseOf('customer');
     * }
     * ```
     *
     * This also may be used for the Order model, but with caution:
     *
     * ```php
     * public function getCustomerQuery()
     * {
     *     return $this->hasOne(Customer::class, ['id' => 'customer_id'])->inverseOf('orders');
     * }
     * ```
     *
     * in this case result will depend on how order(s) was loaded.
     * Let's suppose customer has several orders. If only one order was loaded:
     *
     * ```php
     * $orders = Order::query()->where(['id' => 1])->all();
     * $customerOrders = $orders[0]->customer->orders;
     * ```
     *
     * variable `$customerOrders` will contain only one order. If orders was loaded like this:
     *
     * ```php
     * $orders = Order::query()->with('customer')->where(['customer_id' => 1])->all();
     * $customerOrders = $orders[0]->customer->orders;
     * ```
     *
     * variable `$customerOrders` will contain all orders of the customer.
     *
     * @param string $relationName The name of the relation that is the inverse of this relation.
     *
     * @return static The relation object itself.
     */
    public function inverseOf(string $relationName): static;

    /**
     * @return string|null The name of the relation that is the inverse of this relation.
     */
    public function getInverseOf(): ?string;

    /**
     * Returns related record(s).
     *
     * This method is invoked when a relation of an ActiveRecord is being accessed in a lazy fashion.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws ReflectionException
     * @throws Throwable if the relation is invalid.
     *
     * @return ActiveRecordInterface|ActiveRecordInterface[]|array|array[]|null the related record(s).
     */
    public function relatedRecords(): ActiveRecordInterface|array|null;

    /**
     * Finds an ActiveRecord instance by the given primary key value.
     * In the examples below, the `id` column is the primary key of the table.
     *
     * ```php
     * $customerQuery = Customer::query();
     *
     * $customer = $customerQuery->findByPk(1); // WHERE id = 1
     * ```
     *
     * ```php
     * $customer = $customerQuery->findByPk([1]); // WHERE id = 1
     * ```
     *
     * In the examples below, the `id` and `id2` columns are the composite primary key of the table.
     *
     * ```php
     * $orderItemQuery = OrderItem::query();
     *
     * $orderItem = $orderItemQuery->findByPk([1, 2]); // WHERE id = 1 AND id2 = 2
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
     *     $customerQuery = Customer::query();
     *     $customer = $customerQuery->findByPk($id);
     * }
     * ```
     */
    public function findByPk(array|float|int|string $values): array|ActiveRecordInterface|null;

    /**
     * Returns a value indicating whether the query result rows should be returned as arrays instead of Active Record
     * models.
     */
    public function isAsArray(): bool|null;

    /**
     * It's used to set the query options for the query.
     */
    public function primaryModel(ActiveRecordInterface|null $value): static;

    /**
     * It's used to set the query options for the query.
     *
     * @param string[] $value The columns of the primary and foreign tables that establish a relation.
     * The array keys must be columns of the table for this relation, and the array values must be the corresponding
     * columns from the primary table.
     * Don't prefix or quote the column names as Yii will do this automatically.
     * This property is only used in relational context.
     *
     * @psalm-param array<string, string> $value
     */
    public function link(array $value): static;

    /**
     * It's used to set the query options for the query.
     *
     * @param bool $value Whether this query represents a relation to more than one record.
     * This property is only used in relational context. If true, this relation will populate all query results into AR
     * instances using {@see all()}.
     * If false, only the first row of the results will be retrieved using {@see one()}.
     */
    public function multiple(bool $value): static;

    /**
     * @return ActiveQueryInterface|array|null The query associated with the junction table.
     * Please call {@see via()} to set this property instead of directly setting it.
     *
     * This property is only used in relational context.
     *
     * @see via()
     *
     * @psalm-return Via|null
     */
    public function getVia(): array|self|null;

    /**
     * @return string[] The columns of the primary and foreign tables that establish a relation.
     *
     * The array keys must be columns of the table for this relation, and the array values must be the corresponding
     * columns from the primary table.
     *
     * Don't prefix or quote the column names. Yii does that automatically. This property is only used in
     * relational context.
     *
     * @psalm-return array<string,string>
     */
    public function getLink(): array;

    /**
     * @return ActiveRecordInterface The model instance associated with this query.
     */
    public function getModel(): ActiveRecordInterface;

    /**
     * @return ActiveRecordInterface|null The primary model of a relational query.
     *
     * This is used only in lazy loading with dynamic query options.
     */
    public function getPrimaryModel(): ActiveRecordInterface|null;

    /**
     * @return bool Whether this query represents a relation to more than one record.
     *
     * This property is only used in relational context.
     *
     * If `true`, this relation will populate all query results into active record instances using {@see all()}.
     *
     * If `false`, only the first row of the results will be retrieved using {@see one()}.
     */
    public function isMultiple(): bool;

    /**
     * @inheritdoc
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws ReflectionException
     * @throws Throwable
     *
     * @return ActiveRecordInterface|array|null The first row as an `array` or instance of {@see ActiveRecordInterface}
     * of the query result, depends on {@see isAsArray()} result. `null` if the query results in nothing.
     */
    public function one(): array|ActiveRecordInterface|null;

    /**
     * Finds the related records and populates them into the primary models.
     *
     * @param string $name The relation name.
     * @param ActiveRecordInterface[]|array[] $primaryModels Primary models.
     *
     * @throws Exception
     * @throws InvalidArgumentException|InvalidConfigException|NotSupportedException|Throwable If {@see link()} is
     * invalid.
     * @return ActiveRecordInterface[]|array[] The related models.
     *
     * @psalm-param non-empty-list<ActiveQueryResult> $primaryModels
     * @psalm-param-out non-empty-list<ActiveQueryResult> $primaryModels
     */
    public function populateRelation(string $name, array &$primaryModels): array;
}
