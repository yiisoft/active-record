<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Closure;
use ReflectionException;
use Throwable;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Query\QueryInterface;
use Yiisoft\Definitions\Exception\CircularReferenceException;
use Yiisoft\Definitions\Exception\NotInstantiableException;

/**
 * Defines the common interface to be implemented by active record query classes.
 *
 * That are methods for all normal queries that return active records but also relational queries in which the query
 * represents a relation between two active record classes and will return related records only.
 *
 * A class implementing this interface should also use {@see ActiveQueryTrait} and {@see ActiveRelationTrait}.
 *
 * @psalm-type ARClass = class-string<ActiveRecordInterface>|ActiveRecordInterface|Closure():ActiveRecordInterface
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
     */
    public function all(): array;

    /**
     * Sets the {@see asArray} property.
     *
     * @param bool|null $value Whether to return the query results in terms of arrays instead of Active Records.
     */
    public function asArray(bool|null $value = true): self;

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
     * // Create active query
     * CustomerQuery = new ActiveQuery(Customer::class);
     * // find customers together with their orders and country
     * CustomerQuery->with('orders', 'country')->all();
     * // find customers together with their orders and the orders' shipping address
     * CustomerQuery->with('orders.address')->all();
     * // find customers together with their country and orders of status 1
     * CustomerQuery->with([
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
     * CustomerQuery->with('orders', 'country')->all();
     * CustomerQuery->with('orders')->with('country')->all();
     * ```
     *
     * @param array|string ...$with a list of relation names or relation definitions.
     *
     * @return static the query object itself.
     */
    public function with(array|string ...$with): self;

    /**
     * Specifies the relation associated with the junction table for use in a relational query.
     *
     * @param string $relationName The relation name.
     * This refers to a relation declared in the
     * {@see ActiveRelationTrait::primaryModel} of the relation.
     * @param callable|null $callable A PHP callback for customizing the relation associated with the junction table.
     * Its signature should be `function($query)`, where `$query` is the query to be customized.
     */
    public function via(string $relationName, callable $callable = null): self;

    /**
     * @return array|string|null the join condition to be used when this query is used in a relational context.
     *
     * The condition will be used in the ON part when {@see joinWith()} is called. Otherwise, the condition will be used
     * in the WHERE part of a query.
     *
     * Please refer to {@see Query::where()} on how to specify this parameter.
     *
     * @see onCondition()
     */
    public function getOn(): array|string|null;

    /**
     * @return array $value A list of relations that this query should be joined with.
     */
    public function getJoinWith(): array;

    public function buildJoinWith(): void;

    /**
     * Joins with the specified relations.
     *
     * This method allows you to reuse existing relation definitions to perform JOIN queries. Based on the definition of
     * the specified relation(s), the method will append one or many JOIN statements to the current query.
     *
     * If the `$eagerLoading` parameter is true, the method will also perform eager loading for the specified relations,
     * which is equal to calling {@see with()} using the specified relations.
     *
     * Note: That because a JOIN query will be performed, you're responsible for disambiguated column names.
     *
     * This method differs from {@see with()} in that it will build up and execute a JOIN SQL statement for the primary
     * table. And when `$eagerLoading` is true, it will call {@see with()} in addition with the specified relations.
     *
     * @param array|string $with The relations to be joined. This can either be a string, representing a relation name
     * or an array with the following semantics:
     *
     * - Each array element represents a single relation.
     * - You may specify the relation name as the array key and give anonymous functions that can be used to change the
     *   relation queries on-the-fly as the array value.
     * - If a relation query doesn't need modification, you may use the relation name as the array value.
     *
     * The relation name may optionally contain an alias for the relation table (e.g. `books b`).
     *
     * Sub-relations can also be specified, see {@see with()} for the syntax.
     *
     * In the following you find some examples:
     *
     * ```php
     * // Find all orders that contain books, and eager loading "books".
     * $orderQuery = new ActiveQuery(Order::class);
     * $orderQuery->joinWith('books', true, 'INNER JOIN')->all();
     *
     * // find all orders, eager loading "books", and sort the orders and books by the book names.
     * $orderQuery = new ActiveQuery(Order::class, $db);
     * $orderQuery->joinWith([
     *     'books' => function (ActiveQuery $query) {
     *         $query->orderBy('item.name');
     *     }
     * ])->all();
     *
     * // Find all orders that contain books of the category 'Science fiction', using the alias "b" for the book table.
     * $order = new ActiveQuery(Order::class, $db);
     * $orderQuery->joinWith(['books b'], true, 'INNER JOIN')->where(['b.category' => 'Science fiction'])->all();
     * ```
     * @param array|bool $eagerLoading Whether to eager load the relations specified in `$with`. When this is boolean.
     * It applies to all relations specified in `$with`. Use an array to explicitly list which relations in `$with` a
     * need to be eagerly loaded.
     * Note: That this doesn't mean that the relations are populated from the query result. An
     * extra query will still be performed to bring in the related data. Defaults to `true`.
     * @param array|string $joinType The join type of the relations specified in `$with`.  When this is a string, it
     * applies to all relations specified in `$with`. Use an array in the format of `relationName => joinType` to
     * specify different join types for different relations.
     */
    public function joinWith(
        array|string $with,
        array|bool $eagerLoading = true,
        array|string $joinType = 'LEFT JOIN'
    ): self;

    public function resetJoinWith(): void;

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
     */
    public function innerJoinWith(array|string $with, array|bool $eagerLoading = true): self;

    /**
     * Sets the ON condition for a relational query.
     *
     * The condition will be used in the ON part when {@see joinWith()} is called.
     *
     * Otherwise, the condition will be used in the WHERE part of a query.
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
     * @param array|string $condition The ON condition. Please refer to {@see Query::where()} on how to specify this
     * parameter.
     * @param array $params The parameters (name => value) to be bound to the query.
     */
    public function onCondition(array|string $condition, array $params = []): self;

    /**
     * Adds ON condition to the existing one.
     *
     * The new condition and the existing one will be joined using the 'AND' operator.
     *
     * @param array|string $condition The new ON condition.
     * Please refer to {@see where()} on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query.
     *
     * @see onCondition()
     * @see orOnCondition()
     */
    public function andOnCondition(array|string $condition, array $params = []): self;

    /**
     * Adds ON condition to the existing one.
     *
     * The new condition and the existing one will be joined using the 'OR' operator.
     *
     * @param array|string $condition The new ON condition.
     * Please refer to {@see where()} on how to specify this parameter.
     * @param array $params The parameters (name => value) to be bound to the query.
     *
     * @see onCondition()
     * @see andOnCondition()
     */
    public function orOnCondition(array|string $condition, array $params = []): self;

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
     * @param array $link The link between the junction table and the table associated with {@see primaryModel}.
     * The keys of the array represent the columns in the junction table, and the values represent the columns in the
     * {@see primaryModel} table.
     * @param callable|null $callable A PHP callback for customizing the relation associated with the junction table.
     * Its signature should be `function($query)`, where `$query` is the query to be customized.
     *
     * @see via()
     */
    public function viaTable(string $tableName, array $link, callable $callable = null): self;

    /**
     * Define an alias for the table defined in {@see arClass}.
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
    public function alias(string $alias): self;

    /**
     * Returns table names used in {@see from} indexed by aliases.
     *
     * Both aliases and names are enclosed into {{ and }}.
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

    /**
     * @return ActiveRecordInterface|Closure|string The AR class associated with this query.
     *
     * @psalm-return ARClass
     */
    public function getARClass(): string|ActiveRecordInterface|Closure;

    /**
     * Creates an {@see ActiveQuery} instance with a given SQL statement.
     *
     * Note: That because the SQL statement is already specified, calling more query modification methods
     * (such as {@see where()}, {@see order()) on the created {@see ActiveQuery} instance will have no effect.
     *
     * However, calling {@see with()}, {@see asArray()} or {@see indexBy()} is still fine.
     *
     * Below is an example:
     *
     * ```php
     * $customerQuery = new ActiveQuery(Customer::class, $db);
     * $customers = $customerQuery->findBySql('SELECT * FROM customer')->all();
     * ```
     *
     * @param string $sql The SQL statement to be executed.
     * @param array $params The parameters to be bound to the SQL statement during execution.
     */
    public function findBySql(string $sql, array $params = []): self;

    public function on(array|string|null $value): self;

    public function sql(string|null $value): self;

    /**
     * Converts the raw query results into the format as specified by this query.
     *
     * This method is internally used to convert the data fetched from a database into the format as required by this
     * query.
     *
     * @param array $rows The raw query result from a database.
     *
     * @return array The converted query result.
     */
    public function populate(array $rows, Closure|string|null $indexBy = null): array;

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
     * @return ActiveRecordInterface|array|null the related record(s).
     */
    public function relatedRecords(): ActiveRecordInterface|array|null;

    /**
     * Returns a single active record instance by a primary key or an array of column values.
     *
     * The method accepts:
     *
     *  - A scalar value (integer or string): query by a single primary key value and return the corresponding record
     *    (or `null` if not found).
     *  - A non-associative array: query by a list of primary key values and return the first record (or `null` if not
     *    found).
     *  - An associative array of name-value pairs: query by a set of attribute values and return a single record
     *    matching all them (or `null` if not found).
     *
     * Note that `['id' => 1, 2]` is treated as a non-associative array.
     *
     * Column names are limited to current records' table columns for SQL DBMS, or filtered otherwise to be limited to
     * simple filter conditions.
     *
     * That this method will automatically call the `one()` method and return an {@see ActiveRecordInterface} instance.
     *
     * Note: As this is a shorthand method only, using more complex conditions, `like ['!=', 'id', 1]` will not work.
     * If you need to specify more complex conditions, in combination with {@see ActiveQuery::where()} instead.
     *
     * See the following code for usage examples:
     *
     * ```php
     * // find a single customer whose primary key value is 10
     * $customerQuery = new ActiveQuery(Customer::class, $db);
     * $query = $customerQuery->findOne(10);
     *
     * // the above code is equal to:
     * $customerQuery = new ActiveQuery(Customer::class, $db);
     * $query = $customerQuery->where(['id' => 10])->one();
     *
     * // find the customers whose primary key value is 10, 11 or 12.
     * $customerQuery = new ActiveQuery(Customer::class, $db);
     * $query = $customerQuery->findOne([10, 11, 12]);
     *
     * // the above code is equal to:
     * $customerQuery = new ActiveQuery(Customer::class, $db);
     * $query = $customerQuery->where(['id' => [10, 11, 12]])->one();
     *
     * // find the first customer whose age is 30 and whose status is 1
     * $customerQuery = new ActiveQuery(Customer::class, $db);
     * $query = $customerQuery->findOne(['age' => 30, 'status' => 1]);
     *
     * // the above code is equal to:
     * $customerQuery = new ActiveQuery(Customer::class, $db);
     * $query = $customerQuery->where(['age' => 30, 'status' => 1])->one();
     * ```
     *
     * If you need to pass user input to this method, make sure the input value is scalar or in case of array condition,
     * make sure the array structure can't be changed from the outside:
     *
     * ```php
     * public function actionView(ServerRequestInterface $request)
     * {
     *     $id = (string) $request->getAttribute('id');
     *
     *     $aqClass = new ActiveQuery(Post::class, $db);
     *     $query = $aqClass->findOne($id);
     * }
     *
     * - Explicitly specifying the column to search, passing a scalar or array here will always result in finding a
     * single record:
     *
     * ```php
     * $aqClass = new ActiveQuery(Post::class, $db);
     * $query = $aqClass->findOne(['id' => $id);
     * ```
     *
     * Do NOT use the following code!, it's possible to inject an array condition to filter by arbitrary column values!:
     *
     * ```php
     * $aqClass = new ActiveQuery(Post::class, $db);
     * $query = $aqClass->findOne($id);
     * ```
     *
     * @throws InvalidConfigException
     *
     * @return ActiveRecordInterface|array|null Instance matching the condition, or `null` if nothing matches.
     */
    public function findOne(mixed $condition): array|ActiveRecordInterface|null;

    /**
     * Returns a list of active record that matches the specified primary key value(s) or a set of column values.
     *
     * The method accepts:
     *
     *  - A scalar value (integer or string): query by a single primary key value and return an array containing the
     *    corresponding record (or an empty array if not found).
     *  - A non-associative array: query by a list of primary key values and return the corresponding records (or an
     *    empty array if none was found).
     *    Note that an empty condition will result in an empty result as it will be interpreted as a search for
     *    primary keys and not an empty `WHERE` condition.
     *  - An associative array of name-value pairs: query by a set of attribute values and return an array of records
     *    matching all them (or an empty array if none was found).
     *
     * Note that `['id' => 1, 2]` is treated as a non-associative array.
     *
     * Column names are limited to current records' table columns for SQL DBMS, or filtered otherwise to be limited
     * to simple filter conditions.
     *
     * This method will automatically call the `all()` method and return an array of {@see ActiveRecordInterface}
     * instances.
     *
     * Note: As this is a shorthand method only, using more complex conditions, `like ['!=', 'id', 1]` will not work.
     * If you need to specify more complex conditions, in combination with {@see ActiveQuery::where()} instead.
     *
     * See the following code for usage examples:
     *
     * ```php
     * // find the customers whose primary key value is 10.
     * $customerQuery = new ActiveQuery(Customer::class, $db);
     * $customers = $customerQuery->findAll(10);
     *
     * // the above code is equal to.
     * $customerQuery = new ActiveQuery(Customer::class, $db);
     * $customers = $customerQuery->where(['id' => 10])->all();
     *
     * // find the customers whose primary key value is 10, 11 or 12.
     * $customerQuery = new ActiveQuery(Customer::class, $db);
     * $customers = $customerQuery->findAll([10, 11, 12]);
     *
     * // the above code is equal to,
     * $customerQuery = new ActiveQuery(Customer::class, $db);
     * $customers = $customerQuery->where(['id' => [10, 11, 12]])->all();
     *
     * // find customers whose age is 30 and whose status is 1.
     * $customerQuery = new ActiveQuery(Customer::class, $db);
     * $customers = $customerQuery->findAll(['age' => 30, 'status' => 1]);
     *
     * // the above code is equal to.
     * $customerQuery = new ActiveQuery(Customer::class, $db);
     * $customers = $customerQuery->where(['age' => 30, 'status' => 1])->all();
     * ```
     *
     * If you need to pass user input to this method, make sure the input value is scalar or in case of array condition,
     * make sure the array structure can't be changed from the outside:
     *
     * ```php
     * public function actionView(ServerRequestInterface $request)
     * {
     *     $id = (string) $request->getAttribute('id');
     *
     *     $aqClass = new ActiveQuery(Post::class, $db);
     *     $query = $aqClass->findOne($id);
     * }
     * ```
     *
     * Explicitly specifying the column to search, passing a scalar or array here will always result in finding a single
     * record:
     *
     * ```php
     * $aqClass = new ActiveQuery(Post::class, $db);
     * $aqCLass = $aqClass->findOne(['id' => $id]);
     * ```
     *
     * Do NOT use the following code! It's possible to inject an array condition to filter by arbitrary column values!:
     *
     * ```php
     * $aqClass = new ActiveQuery(Post::class, $db);
     * $aqClass = $aqClass->findOne($id);
     * ```
     *
     * @return array An array of ActiveRecord instance, or an empty array if nothing matches.
     */
    public function findAll(mixed $condition): array;

    /**
     * It's used to set the query options for the query.
     */
    public function primaryModel(ActiveRecordInterface $value): self;

    /**
     * It's used to set the query options for the query.
     *
     * @param array $value The columns of the primary and foreign tables that establish a relation.
     * The array keys must be columns of the table for this relation, and the array values must be the corresponding
     * columns from the primary table.
     * Don't prefix or quote the column names as Yii will do this automatically.
     * This property is only used in relational context.
     */
    public function link(array $value): self;

    /**
     * It's used to set the query options for the query.
     *
     * @param bool $value Whether this query represents a relation to more than one record.
     * This property is only used in relational context. If true, this relation will populate all query results into AR
     * instances using {@see all()}.
     * If false, only the first row of the results will be retrieved using {@see one()}.
     */
    public function multiple(bool $value): self;

    /**
     * @return ActiveQueryInterface|array|null The query associated with the junction table.
     * Please call {@see Actiquery::via} to set this property instead of directly setting it.
     *
     * This property is only used in relational context.
     *
     * @see Actiquery::via
     */
    public function getVia(): array|self|null;

    /**
     * @return array The columns of the primary and foreign tables that establish a relation.
     *
     * The array keys must be columns of the table for this relation, and the array values must be the corresponding
     * columns from the primary table.
     *
     * Do not prefix or quote the column names as this will be done automatically by Yii. This property is only used in
     * relational context.
     *
     * @psalm-return string[]
     */
    public function getLink(): array;

    /**
     * @throws CircularReferenceException
     * @throws InvalidConfigException
     * @throws NotInstantiableException
     * @return ActiveRecordInterface The model instance associated with this query.
     */
    public function getARInstance(): ActiveRecordInterface;

    /**
     * @return bool Whether this query represents a relation to more than one record.
     *
     * This property is only used in relational context.
     *
     * If `true`, this relation will populate all query results into active record instances using
     * {@see all()}.
     *
     * If `false`, only the first row of the results will be retrieved using {@see one()}.
     */
    public function getMultiple(): bool;

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
}
