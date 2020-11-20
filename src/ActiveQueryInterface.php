<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Query\QueryInterface;

/**
 * ActiveQueryInterface defines the common interface to be implemented by active record query classes.
 *
 * That are methods for either normal queries that return active records but also relational queries in which the query
 * represents a relation between two active record classes and will return related records only.
 *
 * A class implementing this interface should also use {@see ActiveQueryTrait} and {@see ActiveRelationTrait}.
 */
interface ActiveQueryInterface extends QueryInterface
{
    /**
     * Sets the {@see asArray} property.
     *
     * @param bool|null $value whether to return the query results in terms of arrays instead of Active Records.
     *
     * @return ActiveQueryInterface the query object itself.
     */
    public function asArray(?bool $value = true): self;

    /**
     * Executes query and returns a single row of result.
     *
     * @return ActiveRecordInterface|array|null a single row of query result. Depending on the setting of {@see asArray}
     * the query result may be either an array or an ActiveRecord object. `null` will be returned if the query results
     * in nothing.
     */
    public function one();

    /**
     * Sets the {@see indexBy} property.
     *
     * @param callable|string $column the name of the column by which the query results should be indexed by.
     *
     * This can also be a callable (e.g. anonymous function) that returns the index value based on the given row or
     * model data. The signature of the callable should be:
     *
     * ```php
     * // $model is an AR instance when `asArray` is false,
     * // or an array of column values when `asArray` is true.
     * function ($model)
     * {
     *     // return the index value corresponding to $model
     * }
     * ```
     *
     * @return Query the query object itself
     */
    public function indexBy($column): Query;

    /**
     * Specifies the relations with which this query should be performed.
     *
     * The parameters to this method can be either one or multiple strings, or a single array of relation names and the
     * optional callbacks to customize the relations.
     *
     * A relation name can refer to a relation defined in {@see ActiveQueryTrait::modelClass|modelClass} or a
     * sub-relation that stands for a relation of a related record.
     *
     * For example, `orders.address` means the `address` relation defined in the model class corresponding to the
     * `orders` relation.
     *
     * The following are some usage examples:
     *
     * ```php
     * // Create active query
     * CustomerQuery = new ActiveQuery(Customer::class, $this->db);
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
     * @param array|string $with
     *
     * @return $this the query object itself
     */
    public function with(...$with): self;

    /**
     * Specifies the relation associated with the junction table for use in relational query.
     *
     * @param string $relationName the relation name. This refers to a relation declared in the
     * {@see ActiveRelationTrait::primaryModel|primaryModel} of the relation.
     * @param callable|null $callable a PHP callback for customizing the relation associated with the junction table.
     * Its signature should be `function($query)`, where `$query` is the query to be customized.
     *
     * @return $this the relation object itself.
     */
    public function via(string $relationName, callable $callable = null): self;

    /**
     * Finds the related records for the specified primary record.
     *
     * This method is invoked when a relation of an ActiveRecord is being accessed in a lazy fashion.
     *
     * @param string $name the relation name.
     * @param ActiveRecordInterface $model the primary model.
     *
     * @return mixed the related record(s).
     */
    public function findFor(string $name, ActiveRecordInterface $model);

    /**
     * Returns a single active record active record instance by a primary key or an array of column values.
     *
     * The method accepts:
     *
     *  - a scalar value (integer or string): query by a single primary key value and return the corresponding record
     *    (or `null` if not found).
     *  - a non-associative array: query by a list of primary key values and return the first record (or `null` if not
     *    found).
     *  - an associative array of name-value pairs: query by a set of attribute values and return a single record
     *    matching all of them (or `null` if not found). Note that `['id' => 1, 2]` is treated as a non-associative
     *    array.
     *
     * Column names are limited to current records table columns for SQL DBMS, or filtered otherwise to be limited to
     * simple filter conditions.
     *
     * That this method will automatically call the `one()` method and return an
     * {@see ActiveRecordInterface|ActiveRecord} instance.
     *
     * > Note: As this is a short-hand method only, using more complex conditions, like ['!=', 'id', 1] will not work.
     * > If you need to specify more complex conditions, in combination with {@see ActiveQuery::where()|where()} instead.
     *
     * See the following code for usage examples:
     *
     * ```php
     * // find a single customer whose primary key value is 10
     * $customerQuery = new ActiveQuery(Customer::class, $db);
     * $query = $customerQuery->findOne(10);
     *
     * // the above code is equivalent to:
     * $customerQuery = new ActiveQuery(Customer::class, $db);
     * $query = $customerQuery->where(['id' => 10])->one();
     *
     * // find the customers whose primary key value is 10, 11 or 12.
     * $customerQuery = new ActiveQuery(Customer::class, $db);
     * $query = $customerQuery->findOne([10, 11, 12]);
     *
     * // the above code is equivalent to:
     * $customerQuery = new ActiveQuery(Customer::class, $db);
     * $query = $customerQuery->where(['id' => [10, 11, 12]])->one();
     *
     * // find the first customer whose age is 30 and whose status is 1
     * $customerQuery = new ActiveQuery(Customer::class, $db);
     * $query = $customerQuery->findOne(['age' => 30, 'status' => 1]);
     *
     * // the above code is equivalent to:
     * $customerQuery = new ActiveQuery(Customer::class, $db);
     * $query = $customerQuery->where(['age' => 30, 'status' => 1])->one();
     * ```
     *
     * If you need to pass user input to this method, make sure the input value is scalar or in case of array condition,
     * make sure the array structure can not be changed from the outside:
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
     * $aqClass = new ActiveQuery(Post::class, $db);
     * $query = $aqClass->findOne(['id' => $id);
     *
     * Do NOT use the following code! it is possible to inject an array condition to filter by arbitrary column
     * values!:
     * $aqClass = new ActiveQuery(Post::class, $db);
     * $query = $aqClass->findOne($id);
     * ```
     *
     * @param mixed $condition primary key value or a set of column values.
     *
     * @return ActiveRecordInterface|null instance matching the condition, or `null` if nothing matches.
     */
    public function findOne($condition): ?ActiveRecordInterface;

    /**
     * Returns a list of active record that match the specified primary key value(s) or a set of column values.
     *
     * The method accepts:
     *
     *  - a scalar value (integer or string): query by a single primary key value and return an array containing the
     *    corresponding record (or an empty array if not found).
     *  - a non-associative array: query by a list of primary key values and return the corresponding records (or an
     *    empty array if none was found).
     *    Note that an empty condition will result in an empty result as it will be interpreted as a search for
     *    primary keys and not an empty `WHERE` condition.
     *  - an associative array of name-value pairs: query by a set of attribute values and return an array of records
     *    matching all of them (or an empty array if none was found). Note that `['id' => 1, 2]` is treated as
     *    a non-associative array.
     *    Column names are limited to current records table columns for SQL DBMS, or filtered otherwise to be limted to
     *    simple filter conditions.
     *
     * This method will automatically call the `all()` method and return an array of
     * {@see ActiveRecordInterface|ActiveRecord} instances.
     *
     * > Note: As this is a short-hand method only, using more complex conditions, like ['!=', 'id', 1] will not work.
     * > If you need to specify more complex conditions, in combination with {@see ActiveQuery::where()|where()} instead.
     *
     * See the following code for usage examples:
     *
     * ```php
     * // find the customers whose primary key value is 10.
     * $customerQuery = new ActiveQuery(Customer::class, $db);
     * $customers = $customerQuery->findAll(10);
     *
     * // the above code is equivalent to.
     * $customerQuery = new ActiveQuery(Customer::class, $db);
     * $customers = $customerQuery->where(['id' => 10])->all();
     *
     * // find the customers whose primary key value is 10, 11 or 12.
     * $customerQuery = new ActiveQuery(Customer::class, $db);
     * $customers = $customerQuery->findAll([10, 11, 12]);
     *
     * // the above code is equivalent to,
     * $customerQuery = new ActiveQuery(Customer::class, $db);
     * $customers = $customerQuery->where(['id' => [10, 11, 12]])->all();
     *
     * // find customers whose age is 30 and whose status is 1.
     * $customerQuery = new ActiveQuery(Customer::class, $db);
     * $customers = $customerQuery->findAll(['age' => 30, 'status' => 1]);
     *
     * // the above code is equivalent to.
     * $customerQuery = new ActiveQuery(Customer::class, $db);
     * $customers = $customerQuery->where(['age' => 30, 'status' => 1])->all();
     * ```
     *
     * If you need to pass user input to this method, make sure the input value is scalar or in case of array condition,
     * make sure the array structure can not be changed from the outside:
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
     * Explicitly specifying the column to search, passing a scalar or array here will always result in finding a single
     * record:
     * $aqClass = new ActiveQuery(Post::class, $db);
     * $aqCLass = $aqClass->findOne(['id' => $id]);
     *
     * Do NOT use the following code! it is possible to inject an array condition to filter by arbitrary column values!:
     * $aqClass = new ActiveQuery(Post::class, $db);
     * $aqClass = $aqClass->findOne($id);
     * ```
     *
     * @param mixed $condition primary key value or a set of column values.
     *
     * @return array an array of ActiveRecord instance, or an empty array if nothing matches.
     */
    public function findAll($condition): array;
}
