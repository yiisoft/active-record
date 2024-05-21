<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use ReflectionException;
use Throwable;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\BitValues;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Category;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Document;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Dossier;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Item;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Order;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\OrderItem;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\OrderItemWithNullFK;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\OrderWithNullFK;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Profile;
use Yiisoft\ActiveRecord\Tests\Support\Assert;
use Yiisoft\ActiveRecord\Tests\Support\DbHelper;
use Yiisoft\Db\Command\AbstractCommand;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidCallException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\StaleObjectException;
use Yiisoft\Db\Exception\UnknownPropertyException;
use Yiisoft\Db\Helper\DbArrayHelper;
use Yiisoft\Db\Query\QueryInterface;

use function sort;
use function ucfirst;

abstract class ActiveQueryTest extends TestCase
{
    public function testOptions(): void
    {
        $this->checkFixture($this->db, 'customer', true);

        $customerQuery = new ActiveQuery(Customer::class, $this->db);

        $query = $customerQuery->on(['a' => 'b'])->joinWith('profile');
        $this->assertEquals($query->getARClass(), Customer::class);
        $this->assertEquals($query->getOn(), ['a' => 'b']);
        $this->assertEquals($query->getJoinWith(), [[['profile'], true, 'LEFT JOIN']]);
        $customerQuery->resetJoinWith();
        $this->assertEquals($query->getJoinWith(), []);
    }

    public function testPrepare(): void
    {
        $this->checkFixture($this->db, 'customer');

        $query = new ActiveQuery(Customer::class, $this->db);
        $this->assertInstanceOf(QueryInterface::class, $query->prepare($this->db->getQueryBuilder()));
    }

    public function testPopulateEmptyRows(): void
    {
        $this->checkFixture($this->db, 'customer');

        $query = new ActiveQuery(Customer::class, $this->db);
        $this->assertEquals([], $query->populate([]));
    }

    public function testPopulateFilledRows(): void
    {
        $this->checkFixture($this->db, 'customer');

        $query = new ActiveQuery(Customer::class, $this->db);
        $rows = $query->all();
        $result = $query->populate($rows);
        $this->assertEquals($rows, $result);
    }

    public function testAllPopulate(): void
    {
        $this->checkFixture($this->db, 'customer');

        $query = new ActiveQuery(Customer::class, $this->db);

        foreach ($query->allPopulate() as $customer) {
            $this->assertInstanceOf(Customer::class, $customer);
        }

        $this->assertCount(3, $query->allPopulate());
    }

    public function testOnePopulate(): void
    {
        $this->checkFixture($this->db, 'customer');

        $query = new ActiveQuery(Customer::class, $this->db);
        $this->assertInstanceOf(Customer::class, $query->onePopulate());
    }

    public function testCreateCommand(): void
    {
        $this->checkFixture($this->db, 'customer');

        $query = new ActiveQuery(Customer::class, $this->db);
        $this->assertInstanceOf(AbstractCommand::class, $query->createCommand());
    }

    public function testQueryScalar(): void
    {
        $this->checkFixture($this->db, 'customer');

        $query = new ActiveQuery(Customer::class, $this->db);
        $this->assertEquals('user1', Assert::invokeMethod($query, 'queryScalar', ['name']));
    }

    public function testGetJoinWith(): void
    {
        $this->checkFixture($this->db, 'customer');

        $query = new ActiveQuery(Customer::class, $this->db);
        $query->joinWith('profile');
        $this->assertEquals([[['profile'], true, 'LEFT JOIN']], $query->getJoinWith());
    }

    public function testInnerJoinWith(): void
    {
        $this->checkFixture($this->db, 'customer');

        $query = new ActiveQuery(Customer::class, $this->db);
        $query->innerJoinWith('profile');
        $this->assertEquals([[['profile'], true, 'INNER JOIN']], $query->getJoinWith());
    }

    public function testBuildJoinWithRemoveDuplicateJoinByTableName(): void
    {
        $this->checkFixture($this->db, 'customer');

        $query = new ActiveQuery(Customer::class, $this->db);
        $query->innerJoinWith('orders')->joinWith('orders.orderItems');
        Assert::invokeMethod($query, 'buildJoinWith');
        $this->assertEquals([
            [
                'INNER JOIN',
                'order',
                '{{customer}}.[[id]] = {{order}}.[[customer_id]]',
            ],
            [
                'LEFT JOIN',
                'order_item',
                '{{order}}.[[id]] = {{order_item}}.[[order_id]]',
            ],
        ], $query->getJoins());
    }

    public function testGetQueryTableNameFromNotSet(): void
    {
        $this->checkFixture($this->db, 'customer');

        $query = new ActiveQuery(Customer::class, $this->db);
        $this->assertEquals(['customer', 'customer'], Assert::invokeMethod($query, 'getTableNameAndAlias'));
    }

    public function testGetQueryTableNameFromSet(): void
    {
        $this->checkFixture($this->db, 'customer');

        $query = new ActiveQuery(Customer::class, $this->db);
        $query->from(['alias' => 'customer']);
        $this->assertEquals(['customer', 'alias'], Assert::invokeMethod($query, 'getTableNameAndAlias'));
    }

    public function testOnCondition(): void
    {
        $this->checkFixture($this->db, 'customer');

        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = new ActiveQuery(Customer::class, $this->db);
        $query->onCondition($on, $params);
        $this->assertEquals($on, $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testAndOnConditionOnNotSet(): void
    {
        $this->checkFixture($this->db, 'customer');

        $on = ['active' => true];
        $params = ['a' => 'b'];
        $query = new ActiveQuery(Customer::class, $this->db);
        $query->andOnCondition($on, $params);
        $this->assertEquals($on, $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testAndOnConditionOnSet(): void
    {
        $this->checkFixture($this->db, 'customer');

        $onOld = ['active' => true];
        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = new ActiveQuery(Customer::class, $this->db);

        $query->on($onOld)->andOnCondition($on, $params);

        $this->assertEquals(['and', $onOld, $on], $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testOrOnConditionOnNotSet(): void
    {
        $this->checkFixture($this->db, 'customer');

        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = new ActiveQuery(Customer::class, $this->db);

        $query->orOnCondition($on, $params);

        $this->assertEquals($on, $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testOrOnConditionOnSet(): void
    {
        $this->checkFixture($this->db, 'customer');

        $onOld = ['active' => true];
        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = new ActiveQuery(Customer::class, $this->db);

        $query->on($onOld)->orOnCondition($on, $params);

        $this->assertEquals(['or', $onOld, $on], $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testViaTable(): void
    {
        $this->checkFixture($this->db, 'customer');

        $order = new Order($this->db);

        $query = new ActiveQuery(Customer::class, $this->db);

        $query->primaryModel($order)->viaTable(Profile::class, ['id' => 'item_id']);

        $this->assertInstanceOf(ActiveQuery::class, $query);
        $this->assertInstanceOf(ActiveQuery::class, $query->getVia());
    }

    public function testAliasNotSet(): void
    {
        $this->checkFixture($this->db, 'customer');

        $query = new ActiveQuery(Customer::class, $this->db);

        $query->alias('alias');

        $this->assertInstanceOf(ActiveQuery::class, $query);
        $this->assertEquals(['alias' => 'customer'], $query->getFrom());
    }

    public function testAliasYetSet(): void
    {
        $this->checkFixture($this->db, 'customer');

        $aliasOld = ['old'];

        $query = new ActiveQuery(Customer::class, $this->db);

        $query->from($aliasOld)->alias('alias');

        $this->assertInstanceOf(ActiveQuery::class, $query);
        $this->assertEquals(['alias' => 'old'], $query->getFrom());
    }

    public function testGetTableNamesNotFilledFrom(): void
    {
        $this->checkFixture($this->db, 'profile');

        $query = new ActiveQuery(Profile::class, $this->db);
        $tableName = Profile::TABLE_NAME;

        $this->assertEquals(
            [
                '{{' . $tableName . '}}' => '{{' . $tableName . '}}',
            ],
            $query->getTablesUsedInFrom()
        );
    }

    public function testGetTableNamesWontFillFrom(): void
    {
        $this->checkFixture($this->db, 'profile');

        $query = new ActiveQuery(Profile::class, $this->db);

        $this->assertSame([], $query->getFrom());

        $query->getTablesUsedInFrom();

        $this->assertSame([], $query->getFrom());
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/5341}
     *
     * Issue: Plan 1 -- * Account * -- * User
     * Our Tests: Category 1 -- * Item * -- * Order
     */
    public function testDeeplyNestedTableRelationWith(): void
    {
        $this->checkFixture($this->db, 'category', true);

        /** @var $category Category */
        $categoriesQuery = new ActiveQuery(Category::class, $this->db);

        $categories = $categoriesQuery->with('orders')->indexBy('id')->all();
        $category = $categories[1];
        $this->assertNotNull($category);

        $orders = $category->orders;
        $this->assertCount(2, $orders);
        $this->assertInstanceOf(Order::class, $orders[0]);
        $this->assertInstanceOf(Order::class, $orders[1]);

        $ids = [$orders[0]->id, $orders[1]->id];
        sort($ids);
        $this->assertEquals([1, 3], $ids);

        $category = $categories[2];
        $this->assertNotNull($category);

        $orders = $category->orders;
        $this->assertCount(1, $orders);
        $this->assertInstanceOf(Order::class, $orders[0]);
        $this->assertEquals(2, $orders[0]->id);
    }

    public function testGetSql(): void
    {
        $this->checkFixture($this->db, 'customer');

        $query = new ActiveQuery(Customer::class, $this->db);

        $query->sql('SELECT * FROM {{customer}} ORDER BY [[id]] DESC');

        $this->assertEquals('SELECT * FROM {{customer}} ORDER BY [[id]] DESC', $query->getSql());
    }

    public function testCustomColumns(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customerQuery = new ActiveQuery(Customer::class, $this->db);

        /** find custom column */
        if ($this->db->getDriverName() === 'oci') {
            $customers = $customerQuery
                ->select(['{{customer}}.*', '([[status]]*2) AS [[status2]]'])
                ->where(['name' => 'user3'])->onePopulate();
        } else {
            $customers = $customerQuery
                ->select(['*', '([[status]]*2) AS [[status2]]'])
                ->where(['name' => 'user3'])->onePopulate();
        }

        $this->assertEquals(3, $customers->getAttribute('id'));
        $this->assertEquals(4, $customers->status2);
    }

    public function testCallFind(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customerQuery = new ActiveQuery(Customer::class, $this->db);

        /** find count, sum, average, min, max, scalar */
        $this->assertEquals(3, $customerQuery->count());
        $this->assertEquals(6, $customerQuery->sum('[[id]]'));
        $this->assertEquals(2, $customerQuery->average('[[id]]'));
        $this->assertEquals(1, $customerQuery->min('[[id]]'));
        $this->assertEquals(3, $customerQuery->max('[[id]]'));
        $this->assertEquals(3, $customerQuery->select('COUNT(*)')->scalar());
        $this->assertEquals(2, $customerQuery->where('[[id]]=1 OR [[id]]=2')->count());
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/8593}
     */
    public function testCountWithFindBySql(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customerQuery = new ActiveQuery(Customer::class, $this->db);

        $query = $customerQuery->findBySql('SELECT * FROM {{customer}}');
        $this->assertEquals(3, $query->count());

        $query = $customerQuery->findBySql('SELECT * FROM {{customer}} WHERE  [[id]]=:id', [':id' => 2]);
        $this->assertEquals(1, $query->count());
    }

    public function testDeeplyNestedTableRelation(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customerQuery = new ActiveQuery(Customer::class, $this->db);

        $customers = $customerQuery->findOne(1);
        $this->assertNotNull($customerQuery);

        $items = $customers->orderItems;

        $this->assertCount(2, $items);
        $this->assertEquals(1, $items[0]->getAttribute('id'));
        $this->assertEquals(2, $items[1]->getAttribute('id'));
        $this->assertInstanceOf(Item::class, $items[0]);
        $this->assertInstanceOf(Item::class, $items[1]);
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/5341}
     *
     * Issue: Plan 1 -- * Account * -- * User
     * Our Tests: Category 1 -- * Item * -- * Order
     */
    public function testDeeplyNestedTableRelation2(): void
    {
        $this->checkFixture($this->db, 'category');

        $categoryQuery = new ActiveQuery(Category::class, $this->db);

        $categories = $categoryQuery->where(['id' => 1])->onePopulate();
        $this->assertNotNull($categories);

        $orders = $categories->orders;
        $this->assertCount(2, $orders);
        $this->assertInstanceOf(Order::class, $orders[0]);
        $this->assertInstanceOf(Order::class, $orders[1]);

        $ids = [$orders[0]->id, $orders[1]->getAttribute('id')];
        sort($ids);
        $this->assertEquals([1, 3], $ids);

        $categories = $categoryQuery->where(['id' => 2])->onePopulate();
        $this->assertNotNull($categories);

        $orders = $categories->orders;
        $this->assertCount(1, $orders);
        $this->assertEquals(2, $orders[0]->getAttribute('id'));
        $this->assertInstanceOf(Order::class, $orders[0]);
    }

    public function testJoinWith(): void
    {
        $this->checkFixture($this->db, 'order');

        /** left join and eager loading */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $orders = $orderQuery->joinWith('customer')->orderBy('customer.id DESC, order.id')->all();
        $this->assertCount(3, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertEquals(1, $orders[2]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));
        $this->assertTrue($orders[2]->isRelationPopulated('customer'));

        /** inner join filtering and eager loading */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $orders = $orderQuery->innerJoinWith(
            [
                'customer' => function ($query) {
                    $query->where('{{customer}}.[[id]]=2');
                },
            ]
        )->orderBy('order.id')->all();
        $this->assertCount(2, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));

        /** inner join filtering, eager loading, conditions on both primary and relation */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $orders = $orderQuery->innerJoinWith(
            [
                'customer' => function ($query) {
                    $query->where(['customer.id' => 2]);
                },
            ]
        )->where(['order.id' => [1, 2]])->orderBy('order.id')->all();
        $this->assertCount(1, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));

        /** inner join filtering without eager loading */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $orders = $orderQuery->innerJoinWith(
            [
                'customer' => static function ($query) {
                    $query->where('{{customer}}.[[id]]=2');
                },
            ],
            false
        )->orderBy('order.id')->all();
        $this->assertCount(2, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertFalse($orders[0]->isRelationPopulated('customer'));
        $this->assertFalse($orders[1]->isRelationPopulated('customer'));

        /** inner join filtering without eager loading, conditions on both primary and relation */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $orders = $orderQuery->innerJoinWith(
            [
                'customer' => static function ($query) {
                    $query->where(['customer.id' => 2]);
                },
            ],
            false
        )->where(['order.id' => [1, 2]])->orderBy('order.id')->all();
        $this->assertCount(1, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertFalse($orders[0]->isRelationPopulated('customer'));

        /** join with via-relation */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $orders = $orderQuery->innerJoinWith('books')->orderBy('order.id')->all();
        $this->assertCount(2, $orders);
        $this->assertCount(2, $orders[0]->books);
        $this->assertCount(1, $orders[1]->books);
        $this->assertEquals(1, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('books'));
        $this->assertTrue($orders[1]->isRelationPopulated('books'));

        /** join with sub-relation */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $orders = $orderQuery->innerJoinWith(
            [
                'items' => function ($q) {
                    $q->orderBy('item.id');
                },
                'items.category' => function ($q) {
                    $q->where('{{category}}.[[id]] = 2');
                },
            ]
        )->orderBy('order.id')->all();
        $this->assertCount(1, $orders);
        $this->assertCount(3, $orders[0]->items);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(2, $orders[0]->items[0]->category->id);
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $this->assertTrue($orders[0]->items[0]->isRelationPopulated('category'));

        /** join with table alias */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $orders = $orderQuery->joinWith(
            [
                'customer' => function ($q) {
                    $q->from('customer c');
                },
            ]
        )->orderBy('c.id DESC, order.id')->all();
        $this->assertCount(3, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertEquals(1, $orders[2]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));
        $this->assertTrue($orders[2]->isRelationPopulated('customer'));

        /** join with table alias */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $orders = $orderQuery->joinWith('customer as c')->orderBy('c.id DESC, order.id')->all();
        $this->assertCount(3, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertEquals(1, $orders[2]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));
        $this->assertTrue($orders[2]->isRelationPopulated('customer'));

        /** join with table alias sub-relation */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $orders = $orderQuery->innerJoinWith(
            [
                'items as t' => function ($q) {
                    $q->orderBy('t.id');
                },
                'items.category as c' => function ($q) {
                    $q->where('{{c}}.[[id]] = 2');
                },
            ]
        )->orderBy('order.id')->all();
        $this->assertCount(1, $orders);
        $this->assertCount(3, $orders[0]->items);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(2, $orders[0]->items[0]->category->id);
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $this->assertTrue($orders[0]->items[0]->isRelationPopulated('category'));

        /** join with ON condition */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $orders = $orderQuery->joinWith('books2')->orderBy('order.id')->all();
        $this->assertCount(3, $orders);
        $this->assertCount(2, $orders[0]->books2);
        $this->assertCount(0, $orders[1]->books2);
        $this->assertCount(1, $orders[2]->books2);
        $this->assertEquals(1, $orders[0]->id);
        $this->assertEquals(2, $orders[1]->id);
        $this->assertEquals(3, $orders[2]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('books2'));
        $this->assertTrue($orders[1]->isRelationPopulated('books2'));
        $this->assertTrue($orders[2]->isRelationPopulated('books2'));

        /** lazy loading with ON condition */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $order = $orderQuery->findOne(1);
        $this->assertCount(2, $order->books2);

        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $order = $orderQuery->findOne(2);
        $this->assertCount(0, $order->books2);

        $order = new ActiveQuery(Order::class, $this->db);
        $order = $order->findOne(3);
        $this->assertCount(1, $order->books2);

        /** eager loading with ON condition */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $orders = $orderQuery->with('books2')->all();
        $this->assertCount(3, $orders);
        $this->assertCount(2, $orders[0]->books2);
        $this->assertCount(0, $orders[1]->books2);
        $this->assertCount(1, $orders[2]->books2);
        $this->assertEquals(1, $orders[0]->id);
        $this->assertEquals(2, $orders[1]->id);
        $this->assertEquals(3, $orders[2]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('books2'));
        $this->assertTrue($orders[1]->isRelationPopulated('books2'));
        $this->assertTrue($orders[2]->isRelationPopulated('books2'));

        /** join with count and query */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $query = $orderQuery->joinWith('customer');
        $count = $query->count();
        $this->assertEquals(3, $count);

        $orders = $query->all();
        $this->assertCount(3, $orders);

        /** {@see https://github.com/yiisoft/yii2/issues/2880} */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $query = $orderQuery->findOne(1);
        $customer = $query->getCustomerQuery()->joinWith(
            [
                'orders' => static function ($q) {
                    $q->orderBy([]);
                },
            ]
        )->onePopulate();
        $this->assertEquals(1, $customer->id);

        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $order = $orderQuery->joinWith(
            [
                'items' => static function ($q) {
                    $q->from(['items' => 'item'])->orderBy('items.id');
                },
            ]
        )->orderBy('order.id')->one();

        /** join with sub-relation called inside Closure */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $orders = $orderQuery->joinWith(
            [
                'items' => static function ($q) {
                    $q->orderBy('item.id');
                    $q->joinWith([
                        'category' => static function ($q) {
                            $q->where('{{category}}.[[id]] = 2');
                        },
                    ]);
                },
            ]
        )->orderBy('order.id')->all();
        $this->assertCount(1, $orders);
        $this->assertCount(3, $orders[0]->items);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(2, $orders[0]->items[0]->category->id);
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $this->assertTrue($orders[0]->items[0]->isRelationPopulated('category'));
    }

    /**
     * @depends testJoinWith
     */
    public function testJoinWithAndScope(): void
    {
        $this->checkFixture($this->db, 'customer');

        /**  hasOne inner join */
        $customer = new CustomerQuery(Customer::class, $this->db);
        $customers = $customer->active()->innerJoinWith('profile')->orderBy('customer.id')->all();
        $this->assertCount(1, $customers);
        $this->assertEquals(1, $customers[0]->id);
        $this->assertTrue($customers[0]->isRelationPopulated('profile'));

        /** hasOne outer join */
        $customer = new CustomerQuery(Customer::class, $this->db);
        $customers = $customer->active()->joinWith('profile')->orderBy('customer.id')->all();
        $this->assertCount(2, $customers);
        $this->assertEquals(1, $customers[0]->id);
        $this->assertEquals(2, $customers[1]->id);
        $this->assertInstanceOf(Profile::class, $customers[0]->profile);
        $this->assertNull($customers[1]->profile);
        $this->assertTrue($customers[0]->isRelationPopulated('profile'));
        $this->assertTrue($customers[1]->isRelationPopulated('profile'));

        /** hasMany */
        $customer = new CustomerQuery(Customer::class, $this->db);
        $customers = $customer->active()->joinWith(
            [
                'orders' => static function ($q) {
                    $q->orderBy('order.id');
                },
            ]
        )->orderBy('customer.id DESC, order.id')->all();
        $this->assertCount(2, $customers);
        $this->assertEquals(2, $customers[0]->id);
        $this->assertEquals(1, $customers[1]->id);
        $this->assertTrue($customers[0]->isRelationPopulated('orders'));
        $this->assertTrue($customers[1]->isRelationPopulated('orders'));
    }

    /**
     * @depends testJoinWith
     *
     * This query will do the same join twice, ensure duplicated JOIN gets removed.
     *
     * {@see https://github.com/yiisoft/yii2/pull/2650}
     */
    public function testJoinWithVia(): void
    {
        $this->checkFixture($this->db, 'order');

        $orderQuery = new ActiveQuery(Order::class, $this->db);

        $this->db->getQueryBuilder()->setSeparator("\n");

        $rows = $orderQuery->joinWith('itemsInOrder1')->joinWith(
            [
                'items' => static function ($q) {
                    $q->orderBy('item.id');
                },
            ]
        )->all();
        $this->assertNotEmpty($rows);
    }

    public static function aliasMethodProvider(): array
    {
        return [
            ['explicit'],
        ];
    }

    /**
     * @depends testJoinWith
     *
     * Tests the alias syntax for joinWith: 'alias' => 'relation'.
     *
     * @dataProvider aliasMethodProvider
     *
     * @param string $aliasMethod whether alias is specified explicitly or using the query syntax {{@tablename}}
     *
     * @throws Exception|InvalidConfigException|Throwable
     */
    public function testJoinWithAlias(string $aliasMethod): void
    {
        $orders = [];
        $this->checkFixture($this->db, 'order');

        /** left join and eager loading */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $query = $orderQuery->joinWith(['customer c']);

        if ($aliasMethod === 'explicit') {
            $orders = $query->orderBy('c.id DESC, order.id')->all();
        } elseif ($aliasMethod === 'querysyntax') {
            $orders = $query->orderBy('{{@customer}}.id DESC, {{@order}}.id')->all();
        } elseif ($aliasMethod === 'applyAlias') {
            $orders = $query->orderBy(
                $query->applyAlias('customer', 'id') . ' DESC,' . $query->applyAlias('order', 'id')
            )->all();
        }

        $this->assertCount(3, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertEquals(1, $orders[2]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));
        $this->assertTrue($orders[2]->isRelationPopulated('customer'));

        /** inner join filtering and eager loading */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $query = $orderQuery->innerJoinWith(['customer c']);

        if ($aliasMethod === 'explicit') {
            $orders = $query->where('{{c}}.[[id]]=2')->orderBy('order.id')->all();
        } elseif ($aliasMethod === 'querysyntax') {
            $orders = $query->where('{{@customer}}.[[id]]=2')->orderBy('{{@order}}.id')->all();
        } elseif ($aliasMethod === 'applyAlias') {
            $orders = $query->where(
                [$query->applyAlias('customer', 'id') => 2]
            )->orderBy($query->applyAlias('order', 'id'))->all();
        }

        $this->assertCount(2, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));

        /** inner join filtering without eager loading */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $query = $orderQuery->innerJoinWith(['customer c'], false);

        if ($aliasMethod === 'explicit') {
            $orders = $query->where('{{c}}.[[id]]=2')->orderBy('order.id')->all();
        } elseif ($aliasMethod === 'querysyntax') {
            $orders = $query->where('{{@customer}}.[[id]]=2')->orderBy('{{@order}}.id')->all();
        } elseif ($aliasMethod === 'applyAlias') {
            $orders = $query->where(
                [$query->applyAlias('customer', 'id') => 2]
            )->orderBy($query->applyAlias('order', 'id'))->all();
        }

        $this->assertCount(2, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertFalse($orders[0]->isRelationPopulated('customer'));
        $this->assertFalse($orders[1]->isRelationPopulated('customer'));

        /** join with via-relation */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $query = $orderQuery->innerJoinWith(['books b']);

        if ($aliasMethod === 'explicit') {
            $orders = $query->where(
                ['b.name' => 'Yii 1.1 Application Development Cookbook']
            )->orderBy('order.id')->all();
        } elseif ($aliasMethod === 'querysyntax') {
            $orders = $query->where(
                ['{{@item}}.name' => 'Yii 1.1 Application Development Cookbook']
            )->orderBy('{{@order}}.id')->all();
        } elseif ($aliasMethod === 'applyAlias') {
            $orders = $query->where(
                [$query->applyAlias('book', 'name') => 'Yii 1.1 Application Development Cookbook']
            )->orderBy($query->applyAlias('order', 'id'))->all();
        }

        $this->assertCount(2, $orders);
        $this->assertCount(2, $orders[0]->books);
        $this->assertCount(1, $orders[1]->books);
        $this->assertEquals(1, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('books'));
        $this->assertTrue($orders[1]->isRelationPopulated('books'));

        /** joining sub relations */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $query = $orderQuery->innerJoinWith(
            [
                'items i' => static function ($q) use ($aliasMethod) {
                    /** @var $q ActiveQuery */
                    if ($aliasMethod === 'explicit') {
                        $q->orderBy('{{i}}.id');
                    } elseif ($aliasMethod === 'querysyntax') {
                        $q->orderBy('{{@item}}.id');
                    } elseif ($aliasMethod === 'applyAlias') {
                        $q->orderBy($q->applyAlias('item', 'id'));
                    }
                },
                'items.category c' => static function ($q) use ($aliasMethod) {
                    /**  @var $q ActiveQuery */
                    if ($aliasMethod === 'explicit') {
                        $q->where('{{c}}.[[id]] = 2');
                    } elseif ($aliasMethod === 'querysyntax') {
                        $q->where('{{@category}}.[[id]] = 2');
                    } elseif ($aliasMethod === 'applyAlias') {
                        $q->where([$q->applyAlias('category', 'id') => 2]);
                    }
                },
            ]
        );

        if ($aliasMethod === 'explicit') {
            $orders = $query->orderBy('{{i}}.id')->all();
        } elseif ($aliasMethod === 'querysyntax') {
            $orders = $query->orderBy('{{@item}}.id')->all();
        } elseif ($aliasMethod === 'applyAlias') {
            $orders = $query->orderBy($query->applyAlias('item', 'id'))->all();
        }

        $this->assertCount(1, $orders);
        $this->assertCount(3, $orders[0]->items);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(2, $orders[0]->items[0]->category->id);
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $this->assertTrue($orders[0]->items[0]->isRelationPopulated('category'));

        /** join with ON condition */
        if ($aliasMethod === 'explicit' || $aliasMethod === 'querysyntax') {
            $relationName = 'books' . ucfirst($aliasMethod);

            $orderQuery = new ActiveQuery(Order::class, $this->db);
            $orders = $orderQuery->joinWith(["$relationName b"])->orderBy('order.id')->all();

            $this->assertCount(3, $orders);
            $this->assertCount(2, $orders[0]->$relationName);
            $this->assertCount(0, $orders[1]->$relationName);
            $this->assertCount(1, $orders[2]->$relationName);
            $this->assertEquals(1, $orders[0]->id);
            $this->assertEquals(2, $orders[1]->id);
            $this->assertEquals(3, $orders[2]->id);
            $this->assertTrue($orders[0]->isRelationPopulated($relationName));
            $this->assertTrue($orders[1]->isRelationPopulated($relationName));
            $this->assertTrue($orders[2]->isRelationPopulated($relationName));
        }

        /** join with ON condition and alias in relation definition */
        if ($aliasMethod === 'explicit' || $aliasMethod === 'querysyntax') {
            $relationName = 'books' . ucfirst($aliasMethod) . 'A';

            $orderQuery = new ActiveQuery(Order::class, $this->db);
            $orders = $orderQuery->joinWith([(string)$relationName])->orderBy('order.id')->all();

            $this->assertCount(3, $orders);
            $this->assertCount(2, $orders[0]->$relationName);
            $this->assertCount(0, $orders[1]->$relationName);
            $this->assertCount(1, $orders[2]->$relationName);
            $this->assertEquals(1, $orders[0]->id);
            $this->assertEquals(2, $orders[1]->id);
            $this->assertEquals(3, $orders[2]->id);
            $this->assertTrue($orders[0]->isRelationPopulated($relationName));
            $this->assertTrue($orders[1]->isRelationPopulated($relationName));
            $this->assertTrue($orders[2]->isRelationPopulated($relationName));
        }

        /** join with count and query */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $query = $orderQuery->joinWith(['customer c']);

        if ($aliasMethod === 'explicit') {
            $count = $query->count('c.id');
        } elseif ($aliasMethod === 'querysyntax') {
            $count = $query->count('{{@customer}}.id');
        } elseif ($aliasMethod === 'applyAlias') {
            $count = $query->count($query->applyAlias('customer', 'id'));
        }

        $this->assertEquals(3, $count);

        $orders = $query->all();
        $this->assertCount(3, $orders);

        /** relational query */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $order = $orderQuery->findOne(1);

        $customerQuery = $order->getCustomerQuery()->innerJoinWith(['orders o'], false);

        if ($aliasMethod === 'explicit') {
            $customer = $customerQuery->where(['o.id' => 1])->onePopulate();
        } elseif ($aliasMethod === 'querysyntax') {
            $customer = $customerQuery->where(['{{@order}}.id' => 1])->onePopulate();
        } elseif ($aliasMethod === 'applyAlias') {
            $customer = $customerQuery->where([$query->applyAlias('order', 'id') => 1])->onePopulate();
        }

        $this->assertEquals(1, $customer->id);
        $this->assertNotNull($customer);

        /** join with sub-relation called inside Closure */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $orders = $orderQuery->joinWith(
            [
                'items' => static function ($q) use ($aliasMethod) {
                    /** @var $q ActiveQuery */
                    $q->orderBy('item.id');
                    $q->joinWith(['category c']);

                    if ($aliasMethod === 'explicit') {
                        $q->where('{{c}}.[[id]] = 2');
                    } elseif ($aliasMethod === 'querysyntax') {
                        $q->where('{{@category}}.[[id]] = 2');
                    } elseif ($aliasMethod === 'applyAlias') {
                        $q->where([$q->applyAlias('category', 'id') => 2]);
                    }
                },
            ]
        )->orderBy('order.id')->all();

        $this->assertCount(1, $orders);
        $this->assertCount(3, $orders[0]->items);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(2, $orders[0]->items[0]->category->id);
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $this->assertTrue($orders[0]->items[0]->isRelationPopulated('category'));
    }

    /**
     * @depends testJoinWith
     */
    public function testJoinWithSameTable(): void
    {
        $this->checkFixture($this->db, 'order');

        /**
         * join with the same table but different aliases alias is defined in the relation definition without eager
         * loading
         */
        $query = new ActiveQuery(Order::class, $this->db);
        $query->joinWith('bookItems', false)->joinWith('movieItems', false)->where(['movies.name' => 'Toy Story']);
        $orders = $query->all();
        $this->assertCount(
            1,
            $orders,
            $query->createCommand()->getRawSql() . print_r($orders, true)
        );
        $this->assertEquals(2, $orders[0]->id);
        $this->assertFalse($orders[0]->isRelationPopulated('bookItems'));
        $this->assertFalse($orders[0]->isRelationPopulated('movieItems'));

        /** with eager loading */
        $query = new ActiveQuery(Order::class, $this->db);
        $query->joinWith('bookItems', true)->joinWith('movieItems', true)->where(['movies.name' => 'Toy Story']);
        $orders = $query->all();
        $this->assertCount(
            1,
            $orders,
            $query->createCommand()->getRawSql() . print_r($orders, true)
        );
        $this->assertCount(0, $orders[0]->bookItems);
        $this->assertCount(3, $orders[0]->movieItems);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('bookItems'));
        $this->assertTrue($orders[0]->isRelationPopulated('movieItems'));

        /**
         * join with the same table but different aliases alias is defined in the call to joinWith() without eager
         * loading
         */
        $query = new ActiveQuery(Order::class, $this->db);
        $query
            ->joinWith(
                [
                    'itemsIndexed books' => static function ($q) {
                        $q->onCondition('books.category_id = 1');
                    },
                ],
                false
            )->joinWith(
                [
                    'itemsIndexed movies' => static function ($q) {
                        $q->onCondition('movies.category_id = 2');
                    },
                ],
                false
            )->where(['movies.name' => 'Toy Story']);
        $orders = $query->all();
        $this->assertCount(
            1,
            $orders,
            $query->createCommand()->getRawSql() . print_r($orders, true)
        );
        $this->assertEquals(2, $orders[0]->id);
        $this->assertFalse($orders[0]->isRelationPopulated('itemsIndexed'));

        /** with eager loading, only for one relation as it would be overwritten otherwise. */
        $query = new ActiveQuery(Order::class, $this->db);
        $query
            ->joinWith(
                [
                    'itemsIndexed books' => static function ($q) {
                        $q->onCondition('books.category_id = 1');
                    },
                ],
                false
            )
            ->joinWith(
                [
                    'itemsIndexed movies' => static function ($q) {
                        $q->onCondition('movies.category_id = 2');
                    },
                ],
                true
            )->where(['movies.name' => 'Toy Story']);
        $orders = $query->all();
        $this->assertCount(1, $orders, $query->createCommand()->getRawSql() . print_r($orders, true));
        $this->assertCount(3, $orders[0]->itemsIndexed);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('itemsIndexed'));

        /** with eager loading, and the other relation */
        $query = new ActiveQuery(Order::class, $this->db);
        $query
            ->joinWith(
                [
                    'itemsIndexed books' => static function ($q) {
                        $q->onCondition('books.category_id = 1');
                    },
                ],
                true
            )
            ->joinWith(
                [
                    'itemsIndexed movies' => static function ($q) {
                        $q->onCondition('movies.category_id = 2');
                    },
                ],
                false
            )
            ->where(['movies.name' => 'Toy Story']);
        $orders = $query->all();
        $this->assertCount(1, $orders, $query->createCommand()->getRawSql() . print_r($orders, true));
        $this->assertCount(0, $orders[0]->itemsIndexed);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('itemsIndexed'));
    }

    /**
     * @depends testJoinWith
     */
    public function testJoinWithDuplicateSimple(): void
    {
        $this->checkFixture($this->db, 'order');

        /** left join and eager loading */
        $orderQuery = new ActiveQuery(Order::class, $this->db);

        $orders = $orderQuery
            ->innerJoinWith('customer')
            ->joinWith('customer')
            ->orderBy('customer.id DESC, order.id')
            ->all();

        $this->assertCount(3, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertEquals(1, $orders[2]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));
        $this->assertTrue($orders[2]->isRelationPopulated('customer'));
    }

    /**
     * @depends testJoinWith
     */
    public function testJoinWithDuplicateCallbackFiltering(): void
    {
        $this->checkFixture($this->db, 'order');

        /** inner join filtering and eager loading */
        $orderQuery = new ActiveQuery(Order::class, $this->db);

        $orders = $orderQuery
            ->innerJoinWith('customer')
            ->joinWith([
                'customer' => function ($query) {
                    $query->where('{{customer}}.[[id]]=2');
                },
            ])->orderBy('order.id')->all();

        $this->assertCount(2, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));
    }

    /**
     * @depends testJoinWith
     */
    public function testJoinWithDuplicateCallbackFilteringConditionsOnPrimary(): void
    {
        $this->checkFixture($this->db, 'order');

        /** inner join filtering, eager loading, conditions on both primary and relation */
        $orderQuery = new ActiveQuery(Order::class, $this->db);

        $orders = $orderQuery
            ->innerJoinWith('customer')
            ->joinWith([
                'customer' => function ($query) {
                    $query->where(['{{customer}}.[[id]]' => 2]);
                },
            ])->where(['order.id' => [1, 2]])->orderBy('order.id')->all();

        $this->assertCount(1, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
    }

    /**
     * @depends testJoinWith
     */
    public function testJoinWithDuplicateWithSubRelation(): void
    {
        $this->checkFixture($this->db, 'order');

        /** join with sub-relation */
        $orderQuery = new ActiveQuery(Order::class, $this->db);

        $orders = $orderQuery
            ->innerJoinWith('items')
            ->joinWith([
                'items.category' => function ($q) {
                    $q->where('{{category}}.[[id]] = 2');
                },
            ])->orderBy('order.id')->all();

        $this->assertCount(1, $orders);
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $this->assertEquals(2, $orders[0]->id);
        $this->assertCount(3, $orders[0]->items);
        $this->assertTrue($orders[0]->items[0]->isRelationPopulated('category'));
        $this->assertEquals(2, $orders[0]->items[0]->category->id);
    }

    /**
     * @depends testJoinWith
     */
    public function testJoinWithDuplicateTableAlias1(): void
    {
        $this->checkFixture($this->db, 'order');

        /** join with table alias */
        $orderQuery = new ActiveQuery(Order::class, $this->db);

        $orders = $orderQuery
            ->innerJoinWith('customer')
            ->joinWith([
                'customer' => function ($q) {
                    $q->from('customer c');
                },
            ])->orderBy('c.id DESC, order.id')->all();

        $this->assertCount(3, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertEquals(1, $orders[2]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));
        $this->assertTrue($orders[2]->isRelationPopulated('customer'));
    }

    /**
     * @depends testJoinWith
     */
    public function testJoinWithDuplicateTableAlias2(): void
    {
        $this->checkFixture($this->db, 'order');

        /** join with table alias */
        $orderQuery = new ActiveQuery(Order::class, $this->db);

        $orders = $orderQuery
            ->innerJoinWith('customer')
            ->joinWith('customer as c')
            ->orderBy('c.id DESC, order.id')
            ->all();

        $this->assertCount(3, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertEquals(1, $orders[2]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));
        $this->assertTrue($orders[2]->isRelationPopulated('customer'));
    }

    /**
     * @depends testJoinWith
     */
    public function testJoinWithDuplicateTableAliasSubRelation(): void
    {
        $this->checkFixture($this->db, 'order');

        /** join with table alias sub-relation */
        $orderQuery = new ActiveQuery(Order::class, $this->db);

        $orders = $orderQuery
            ->innerJoinWith([
                'items as t' => function ($q) {
                    $q->orderBy('t.id');
                },
            ])
            ->joinWith([
                'items.category as c' => function ($q) {
                    $q->where('{{c}}.[[id]] = 2');
                },
            ])->orderBy('order.id')->all();

        $this->assertCount(1, $orders);
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $this->assertEquals(2, $orders[0]->id);
        $this->assertCount(3, $orders[0]->items);
        $this->assertTrue($orders[0]->items[0]->isRelationPopulated('category'));
        $this->assertEquals(2, $orders[0]->items[0]->category->id);
    }

    /**
     * @depends testJoinWith
     */
    public function testJoinWithDuplicateSubRelationCalledInsideClosure(): void
    {
        $this->checkFixture($this->db, 'order');

        /** join with sub-relation called inside Closure */
        $orderQuery = new ActiveQuery(Order::class, $this->db);

        $orders = $orderQuery
            ->innerJoinWith('items')
            ->joinWith([
                'items' => function ($q) {
                    $q->orderBy('item.id');
                    $q->joinWith([
                        'category' => function ($q) {
                            $q->where('{{category}}.[[id]] = 2');
                        },
                    ]);
                },
            ])
            ->orderBy('order.id')
            ->all();

        $this->assertCount(1, $orders);
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $this->assertEquals(2, $orders[0]->id);
        $this->assertCount(3, $orders[0]->items);
        $this->assertTrue($orders[0]->items[0]->isRelationPopulated('category'));
        $this->assertEquals(2, $orders[0]->items[0]->category->id);
    }

    public function testAlias(): void
    {
        $this->checkFixture($this->db, 'order');

        $order = new Order($this->db);

        $query = new ActiveQuery(Order::class, $this->db);
        $this->assertSame([], $query->getFrom());

        $query->alias('o');
        $this->assertEquals(['o' => Order::TABLE_NAME], $query->getFrom());

        $query->alias('o')->alias('ord');
        $this->assertEquals(['ord' => Order::TABLE_NAME], $query->getFrom());

        $query->from(['users', 'o' => Order::TABLE_NAME])->alias('ord');
        $this->assertEquals(['users', 'ord' => Order::TABLE_NAME], $query->getFrom());
    }

    public function testInverseOf(): void
    {
        $this->checkFixture($this->db, 'customer');

        /** eager loading: find one and all */
        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customer = $customerQuery->with('orders2')->where(['id' => 1])->onePopulate();
        $this->assertSame($customer->orders2[0]->customer2, $customer);

        //$customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customers = $customerQuery->with('orders2')->where(['id' => [1, 3]])->all();
        $this->assertEmpty($customers[1]->orders2);
        $this->assertSame($customers[0]->orders2[0]->customer2, $customers[0]);

        /** lazy loading */
        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customer = $customerQuery->findOne(2);
        $orders = $customer->orders2;
        $this->assertCount(2, $orders);
        $this->assertSame($customer->orders2[0]->customer2, $customer);
        $this->assertSame($customer->orders2[1]->customer2, $customer);

        /** ad-hoc lazy loading */
        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customer = $customerQuery->findOne(2);
        $orders = $customer->getOrders2Query()->all();
        $this->assertCount(2, $orders);
        $this->assertSame($orders[0]->customer2, $customer);
        $this->assertSame($orders[1]->customer2, $customer);
        $this->assertTrue(
            $orders[0]->isRelationPopulated('customer2'),
            'inverse relation did not populate the relation'
        );
        $this->assertTrue(
            $orders[1]->isRelationPopulated('customer2'),
            'inverse relation did not populate the relation'
        );

        /** the other way around */
        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customer = $customerQuery->with('orders2')->where(['id' => 1])->asArray()->onePopulate();
        $this->assertSame($customer['orders2'][0]['customer2']['id'], $customer['id']);

        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customers = $customerQuery->with('orders2')->where(['id' => [1, 3]])->asArray()->all();
        $this->assertSame($customer['orders2'][0]['customer2']['id'], $customers[0]['id']);
        $this->assertEmpty($customers[1]['orders2']);

        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $orders = $orderQuery->with('customer2')->where(['id' => 1])->all();
        $this->assertSame($orders[0]->customer2->orders2, [$orders[0]]);

        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $order = $orderQuery->with('customer2')->where(['id' => 1])->onePopulate();
        $this->assertSame($order->customer2->orders2, [$order]);

        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $orders = $orderQuery->with('customer2')->where(['id' => 1])->asArray()->all();
        $this->assertSame($orders[0]['customer2']['orders2'][0]['id'], $orders[0]['id']);

        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $order = $orderQuery->with('customer2')->where(['id' => 1])->asArray()->onePopulate();
        $this->assertSame($order['customer2']['orders2'][0]['id'], $orders[0]['id']);

        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $orders = $orderQuery->with('customer2')->where(['id' => [1, 3]])->all();
        $this->assertSame($orders[0]->customer2->orders2, [$orders[0]]);
        $this->assertSame($orders[1]->customer2->orders2, [$orders[1]]);

        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $orders = $orderQuery->with('customer2')->where(['id' => [2, 3]])->orderBy('id')->all();
        $this->assertSame($orders[0]->customer2->orders2, $orders);
        $this->assertSame($orders[1]->customer2->orders2, $orders);

        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $orders = $orderQuery->with('customer2')->where(['id' => [2, 3]])->orderBy('id')->asArray()->all();
        $this->assertSame($orders[0]['customer2']['orders2'][0]['id'], $orders[0]['id']);
        $this->assertSame($orders[0]['customer2']['orders2'][1]['id'], $orders[1]['id']);
        $this->assertSame($orders[1]['customer2']['orders2'][0]['id'], $orders[0]['id']);
        $this->assertSame($orders[1]['customer2']['orders2'][1]['id'], $orders[1]['id']);
    }

    public function testUnlinkAllViaTable(): void
    {
        $this->checkFixture($this->db, 'order', true);

        /** via table with delete. */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $order = $orderQuery->findOne(1);
        $this->assertCount(2, $order->booksViaTable);

        $orderItemQuery = new ActiveQuery(OrderItem::class, $this->db);
        $orderItemCount = $orderItemQuery->count();

        $itemQuery = new ActiveQuery(Item::class, $this->db);
        $this->assertEquals(5, $itemQuery->count());

        $order->unlinkAll('booksViaTable', true);
        $this->assertEquals(5, $itemQuery->count());
        $this->assertEquals($orderItemCount - 2, $orderItemQuery->count());
        $this->assertCount(0, $order->booksViaTable);

        /** via table without delete */
        $this->assertCount(2, $order->booksWithNullFKViaTable);

        $orderItemsWithNullFKQuery = new ActiveQuery(OrderItemWithNullFK::class, $this->db);
        $orderItemCount = $orderItemsWithNullFKQuery->count();
        $this->assertEquals(5, $itemQuery->count());

        $order->unlinkAll('booksWithNullFKViaTable', false);
        $this->assertCount(0, $order->booksWithNullFKViaTable);
        $this->assertEquals(2, $orderItemsWithNullFKQuery->where(
            ['AND', ['item_id' => [1, 2]], ['order_id' => null]]
        )->count());

        $orderItemsWithNullFKQuery = new ActiveQuery(OrderItemWithNullFK::class, $this->db);
        $this->assertEquals($orderItemCount, $orderItemsWithNullFKQuery->count());
        $this->assertEquals(5, $itemQuery->count());
    }

    public function testIssues(): void
    {
        $this->checkFixture($this->db, 'category', true);

        /** {@see https://github.com/yiisoft/yii2/issues/4938} */
        $categoryQuery = new ActiveQuery(Category::class, $this->db);
        $category = $categoryQuery->findOne(2);
        $this->assertInstanceOf(Category::class, $category);
        $this->assertEquals(3, $category->getItemsQuery()->count());
        $this->assertEquals(1, $category->getLimitedItemsQuery()->count());
        $this->assertEquals(1, $category->getLimitedItemsQuery()->distinct(true)->count());

        /** {@see https://github.com/yiisoft/yii2/issues/3197} */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $orders = $orderQuery->with('orderItems')->orderBy('id')->all();
        $this->assertCount(3, $orders);
        $this->assertCount(2, $orders[0]->orderItems);
        $this->assertCount(3, $orders[1]->orderItems);
        $this->assertCount(1, $orders[2]->orderItems);

        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $orders = $orderQuery->with(
            [
                'orderItems' => static function ($q) {
                    $q->indexBy('item_id');
                },
            ]
        )->orderBy('id')->all();
        $this->assertCount(3, $orders);
        $this->assertCount(2, $orders[0]->orderItems);
        $this->assertCount(3, $orders[1]->orderItems);
        $this->assertCount(1, $orders[2]->orderItems);

        /** {@see https://github.com/yiisoft/yii2/issues/8149} */
        $arClass = new Customer($this->db);

        $arClass->name = 'test';
        $arClass->email = 'test';
        $arClass->save();

        $arClass->updateCounters(['status' => 1]);
        $this->assertEquals(1, $arClass->status);
    }

    public function testPopulateWithoutPk(): void
    {
        $this->checkFixture($this->db, 'customer', true);

        /** tests with single pk asArray */
        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $aggregation = $customerQuery
            ->select(['{{customer}}.[[status]]', 'SUM({{order}}.[[total]]) AS [[sumtotal]]'])
            ->joinWith('ordersPlain', false)
            ->groupBy('{{customer}}.[[status]]')
            ->orderBy('status')
            ->asArray()
            ->all();

        $expected = [
            [
                'status' => 1,
                'sumtotal' => 183,
            ],
            [
                'status' => 2,
                'sumtotal' => 0,
            ],
        ];

        $this->assertEquals($expected, $aggregation);

        // tests with single pk asArray with eager loading
        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $aggregation = $customerQuery
            ->select(['{{customer}}.[[status]]', 'SUM({{order}}.[[total]]) AS [[sumtotal]]'])
            ->joinWith('ordersPlain')
            ->groupBy('{{customer}}.[[status]]')
            ->orderBy('status')
            ->asArray()
            ->all();

        $expected = [
            [
                'status' => 1,
                'sumtotal' => 183,
                'ordersPlain' => [],
            ],
            [
                'status' => 2,
                'sumtotal' => 0,
                'ordersPlain' => [],
            ],
        ];
        $this->assertEquals($expected, $aggregation);

        /** tests with single pk with Models */
        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $aggregation = $customerQuery
            ->select(['{{customer}}.[[status]]', 'SUM({{order}}.[[total]]) AS [[sumTotal]]'])
            ->joinWith('ordersPlain', false)
            ->groupBy('{{customer}}.[[status]]')
            ->orderBy('status')
            ->all();

        $this->assertCount(2, $aggregation);
        $this->assertContainsOnlyInstancesOf(Customer::class, $aggregation);

        foreach ($aggregation as $item) {
            if ($item->status === 1) {
                $this->assertEquals(183, $item->sumTotal);
            } elseif ($item->status === 2) {
                $this->assertEquals(0, $item->sumTotal);
            }
        }

        /** tests with composite pk asArray */
        $orderItemQuery = new ActiveQuery(OrderItem::class, $this->db);
        $aggregation = $orderItemQuery
            ->select(['[[order_id]]', 'SUM([[subtotal]]) AS [[subtotal]]'])
            ->joinWith('order', false)
            ->groupBy('[[order_id]]')
            ->orderBy('[[order_id]]')
            ->asArray()
            ->all();

        $expected = [
            [
                'order_id' => 1,
                'subtotal' => 70,
            ],
            [
                'order_id' => 2,
                'subtotal' => 33,
            ],
            [
                'order_id' => 3,
                'subtotal' => 40,
            ],
        ];
        $this->assertEquals($expected, $aggregation);

        /** tests with composite pk with Models */
        $orderItemQuery = new ActiveQuery(OrderItem::class, $this->db);
        $aggregation = $orderItemQuery
            ->select(['[[order_id]]', 'SUM([[subtotal]]) AS [[subtotal]]'])
            ->joinWith('order', false)
            ->groupBy('[[order_id]]')
            ->orderBy('[[order_id]]')
            ->all();

        $this->assertCount(3, $aggregation);
        $this->assertContainsOnlyInstancesOf(OrderItem::class, $aggregation);

        foreach ($aggregation as $item) {
            if ($item->order_id === 1) {
                $this->assertEquals(70, $item->subtotal);
            } elseif ($item->order_id === 2) {
                $this->assertEquals(33, $item->subtotal);
            } elseif ($item->order_id === 3) {
                $this->assertEquals(40, $item->subtotal);
            }
        }
    }

    public function testLinkWhenRelationIsIndexed2(): void
    {
        $this->checkFixture($this->db, 'order');

        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $order = $orderQuery->with('orderItems2')->where(['id' => 1])->onePopulate();

        $orderItem = new OrderItem($this->db);

        $orderItem->order_id = $order->id;
        $orderItem->item_id = 3;
        $orderItem->quantity = 1;
        $orderItem->subtotal = 10.0;

        $order->link('orderItems2', $orderItem);
        $this->assertTrue(isset($order->orderItems2['3']));
    }

    public function testEmulateExecution(): void
    {
        $this->checkFixture($this->db, 'order');

        $customerQuery = new ActiveQuery(Customer::class, $this->db);

        $this->assertGreaterThan(0, $customerQuery->count());
        $this->assertSame([], $customerQuery->emulateExecution()->all());
        $this->assertNull($customerQuery->emulateExecution()->one());
        $this->assertFalse($customerQuery->emulateExecution()->exists());
        $this->assertSame(0, $customerQuery->emulateExecution()->count());
        $this->assertNull($customerQuery->emulateExecution()->sum('id'));
        $this->assertNull($customerQuery->emulateExecution()->average('id'));
        $this->assertNull($customerQuery->emulateExecution()->max('id'));
        $this->assertNull($customerQuery->emulateExecution()->min('id'));
        $this->assertNull($customerQuery->select(['id'])->emulateExecution()->scalar());
        $this->assertSame([], $customerQuery->select(['id'])->emulateExecution()->column());
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/12213}
     */
    public function testUnlinkAllOnCondition(): void
    {
        $this->checkFixture($this->db, 'item');

        /** Ensure there are three items with category_id = 2 in the Items table */
        $itemQuery = new ActiveQuery(Item::class, $this->db);
        $itemsCount = $itemQuery->where(['category_id' => 2])->count();
        $this->assertEquals(3, $itemsCount);

        $categoryQuery = new ActiveQuery(Category::class, $this->db);
        $categoryQuery = $categoryQuery->with('limitedItems')->where(['id' => 2]);

        /**
         * Ensure that limitedItems relation returns only one item (category_id = 2 and id in (1,2,3))
         */
        $category = $categoryQuery->onePopulate();
        $this->assertCount(1, $category->limitedItems);

        /** Unlink all items in the limitedItems relation */
        $category->unlinkAll('limitedItems', true);

        /** Make sure that only one item was unlinked */
        $itemsCount = $itemQuery->where(['category_id' => 2])->count();
        $this->assertEquals(2, $itemsCount);

        /** Call $categoryQuery again to ensure no items were found */
        $this->assertCount(0, $categoryQuery->onePopulate()->limitedItems);
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/12213}
     */
    public function testUnlinkAllOnConditionViaTable(): void
    {
        $this->checkFixture($this->db, 'item', true);

        /** Ensure there are three items with category_id = 2 in the Items table */
        $itemQuery = new ActiveQuery(Item::class, $this->db);
        $itemsCount = $itemQuery->where(['category_id' => 2])->count();
        $this->assertEquals(3, $itemsCount);

        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $orderQuery = $orderQuery->with('limitedItems')->where(['id' => 2]);

        /**
         * Ensure that limitedItems relation returns only one item (category_id = 2 and id in (4, 5)).
         */
        $category = $orderQuery->onePopulate();
        $this->assertCount(2, $category->limitedItems);

        /** Unlink all items in the limitedItems relation */
        $category->unlinkAll('limitedItems', true);

        /** Call $orderQuery again to ensure that links are removed */
        $this->assertCount(0, $orderQuery->onePopulate()->limitedItems);

        /** Make sure that only links were removed, the items were not removed */
        $this->assertEquals(3, $itemQuery->where(['category_id' => 2])->count());
    }

    /**
     * {@see https://github.com/yiisoft/yii2/pull/13891}
     */
    public function testIndexByAfterLoadingRelations(): void
    {
        $this->checkFixture($this->db, 'order');

        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $orderQuery->with('customer')->indexBy(function (Order $order) {
            $this->assertTrue($order->isRelationPopulated('customer'));
            $this->assertNotEmpty($order->customer->id);

            return $order->customer->id;
        })->all();

        $orders = $orderQuery->with('customer')->indexBy('customer.id')->all();

        foreach ($orders as $customer_id => $order) {
            $this->assertEquals($customer_id, $order->customer_id);
        }
    }

    public static function filterTableNamesFromAliasesProvider(): array
    {
        return [
            'table name as string' => ['customer', []],
            'table name as array' => [['customer'], []],
            'table names' => [['customer', 'order'], []],
            'table name and a table alias' => [['customer', 'ord' => 'order'], ['ord']],
            'table alias' => [['csr' => 'customer'], ['csr']],
            'table aliases' => [['csr' => 'customer', 'ord' => 'order'], ['csr', 'ord']],
        ];
    }

    /**
     * @dataProvider filterTableNamesFromAliasesProvider
     *
     * @param $expectedAliases
     *
     * @throws ReflectionException
     */
    public function testFilterTableNamesFromAliases(array|string $fromParams, array $expectedAliases): void
    {
        $this->checkFixture($this->db, 'customer');

        $customerQuery = new ActiveQuery(Customer::class, $this->db);

        $query = $customerQuery->from($fromParams);

        $aliases = Assert::invokeMethod(new Customer($this->db), 'filterValidAliases', [$query]);

        $this->assertEquals($expectedAliases, $aliases);
    }

    public function testExtraFields(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customerQuery = new ActiveQuery(Customer::class, $this->db);

        $query = $customerQuery->with('orders2')->where(['id' => 1])->onePopulate();
        $this->assertCount(1, $query->getRelatedRecords());
        $this->assertCount(1, $query->extraFields());
        $this->assertArrayHasKey('orders2', $query->getRelatedRecords());
        $this->assertContains('orders2', $query->extraFields());
    }

    public static function tableNameProvider(): array
    {
        return [
            ['order', 'order_item'],
            ['order', '{{%order_item}}'],
            ['{{%order}}', 'order_item'],
            ['{{%order}}', '{{%order_item}}'],
        ];
    }

    /**
     * Test whether conditions are quoted correctly in conditions where joinWith is used.
     *
     * {@see https://github.com/yiisoft/yii2/issues/11088}
     *
     * @dataProvider tableNameProvider
     *
     * @throws Exception|InvalidConfigException
     */
    public function testRelationWhereParams(string $orderTableName, string $orderItemTableName): void
    {
        $driverName = $this->db->getDriverName();

        $this->checkFixture($this->db, 'order');

        $order = new Order(db: $this->db, tableName: $orderTableName);
        $orderItem = new OrderItem(db: $this->db, tableName: $orderItemTableName);

        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $order = $orderQuery->findOne(1);
        $itemsSQL = $order->getOrderItemsQuery()->createCommand()->getRawSql();
        $expectedSQL = DbHelper::replaceQuotes(
            <<<SQL
            SELECT * FROM [[order_item]] WHERE [[order_id]]=1
            SQL,
            $driverName,
        );
        $this->assertEquals($expectedSQL, $itemsSQL);

        $order = $orderQuery->findOne(1);
        $itemsSQL = $order->getOrderItemsQuery()->joinWith('item')->createCommand()->getRawSql();
        $expectedSQL = DbHelper::replaceQuotes(
            <<<SQL
            SELECT [[order_item]].* FROM [[order_item]] LEFT JOIN [[item]] ON [[order_item]].[[item_id]] = [[item]].[[id]] WHERE [[order_item]].[[order_id]]=1
            SQL,
            $driverName,
        );
        $this->assertEquals($expectedSQL, $itemsSQL);
    }

    public function testOutdatedRelationsAreResetForExistingRecords(): void
    {
        $this->checkFixture($this->db, 'order_item', true);

        $orderItemQuery = new ActiveQuery(OrderItem::class, $this->db);
        $orderItems = $orderItemQuery->findOne(1);
        $this->assertEquals(1, $orderItems->order->id);
        $this->assertEquals(1, $orderItems->item->id);

        /** test `__set()`. */
        $orderItems->order_id = 2;
        $orderItems->item_id = 1;
        $this->assertEquals(2, $orderItems->order->id);
        $this->assertEquals(1, $orderItems->item->id);

        /** Test `setAttribute()`. */
        $orderItems->setAttribute('order_id', 3);
        $orderItems->setAttribute('item_id', 1);
        $this->assertEquals(3, $orderItems->order->id);
        $this->assertEquals(1, $orderItems->item->id);
    }

    public function testOutdatedCompositeKeyRelationsAreReset(): void
    {
        $this->checkFixture($this->db, 'dossier');

        $dossierQuery = new ActiveQuery(Dossier::class, $this->db);

        $dossiers = $dossierQuery->findOne(['department_id' => 1, 'employee_id' => 1]);
        $this->assertEquals('John Doe', $dossiers->employee->fullName);

        $dossiers->department_id = 2;
        $this->assertEquals('Ann Smith', $dossiers->employee->fullName);

        $dossiers->employee_id = 2;
        $this->assertEquals('Will Smith', $dossiers->employee->fullName);

        unset($dossiers->employee_id);
        $this->assertNull($dossiers->employee);

        $dossier = new Dossier($this->db);
        $this->assertNull($dossier->employee);

        $dossier->employee_id = 1;
        $dossier->department_id = 2;
        $this->assertEquals('Ann Smith', $dossier->employee->fullName);

        $dossier->employee_id = 2;
        $this->assertEquals('Will Smith', $dossier->employee->fullName);
    }

    public function testOutdatedViaTableRelationsAreReset(): void
    {
        $this->checkFixture($this->db, 'order', true);

        $orderQuery = new ActiveQuery(Order::class, $this->db);

        $orders = $orderQuery->findOne(1);
        $orderItemIds = DbArrayHelper::getColumn($orders->items, 'id');
        sort($orderItemIds);
        $this->assertSame([1, 2], $orderItemIds);

        $orders->id = 2;
        sort($orderItemIds);
        $orderItemIds = DbArrayHelper::getColumn($orders->items, 'id');
        $this->assertSame([3, 4, 5], $orderItemIds);

        unset($orders->id);
        $this->assertSame([], $orders->items);

        $order = new Order($this->db);
        $this->assertSame([], $order->items);

        $order->id = 3;
        $orderItemIds = DbArrayHelper::getColumn($order->items, 'id');
        $this->assertSame([2], $orderItemIds);
    }

    public function testInverseOfDynamic(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customerQuery = new ActiveQuery(Customer::class, $this->db);

        $customer = $customerQuery->findOne(1);

        /** request the inverseOf relation without explicitly (eagerly) loading it */
        $orders2 = $customer->getOrders2Query()->all();
        $this->assertSame($customer, $orders2[0]->customer2);

        $orders2 = $customer->getOrders2Query()->onePopulate();
        $this->assertSame($customer, $orders2->customer2);

        /**
         * request the inverseOf relation while also explicitly eager loading it (while possible, this is of course
         * redundant)
         */
        $orders2 = $customer->getOrders2Query()->with('customer2')->all();
        $this->assertSame($customer, $orders2[0]->customer2);

        $orders2 = $customer->getOrders2Query()->with('customer2')->onePopulate();
        $this->assertSame($customer, $orders2->customer2);

        /** request the inverseOf relation as array */
        $orders2 = $customer->getOrders2Query()->asArray()->all();
        $this->assertEquals($customer['id'], $orders2[0]['customer2']['id']);

        $orders2 = $customer->getOrders2Query()->asArray()->onePopulate();
        $this->assertEquals($customer['id'], $orders2['customer2']['id']);
    }

    public function testOptimisticLock(): void
    {
        $this->checkFixture($this->db, 'document');

        $documentQuery = new ActiveQuery(Document::class, $this->db);
        $record = $documentQuery->findOne(1);

        $record->content = 'New Content';
        $record->save();
        $this->assertEquals(1, $record->version);

        $record = $documentQuery->findOne(1);

        $record->content = 'Rewrite attempt content';
        $record->version = 0;
        $this->expectException(StaleObjectException::class);
        $record->save();
    }

    public function testOptimisticLockOnDelete(): void
    {
        $this->checkFixture($this->db, 'document', true);

        $documentQuery = new ActiveQuery(Document::class, $this->db);
        $document = $documentQuery->findOne(1);

        $this->assertSame(0, $document->version);

        $document->version = 1;

        $this->expectException(StaleObjectException::class);
        $document->delete();
    }

    public function testOptimisticLockAfterDelete(): void
    {
        $this->checkFixture($this->db, 'document', true);

        $documentQuery = new ActiveQuery(Document::class, $this->db);
        $document = $documentQuery->findOne(1);

        $this->assertSame(0, $document->version);
        $this->assertSame(1, $document->delete());
        $this->assertTrue($document->getIsNewRecord());

        $this->expectException(StaleObjectException::class);
        $document->delete();
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/9006}
     */
    public function testBit(): void
    {
        $this->checkFixture($this->db, 'bit_values');

        $bitValueQuery = new ActiveQuery(BitValues::class, $this->db);
        $falseBit = $bitValueQuery->findOne(1);
        $this->assertEquals(false, $falseBit->val);

        $bitValueQuery = new ActiveQuery(BitValues::class, $this->db);
        $trueBit = $bitValueQuery->findOne(2);
        $this->assertEquals(true, $trueBit->val);
    }

    public function testUpdateAttributes(): void
    {
        $this->checkFixture($this->db, 'order');

        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $order = $orderQuery->findOne(1);
        $newTotal = 978;
        $this->assertSame(1, $order->updateAttributes(['total' => $newTotal]));
        $this->assertEquals($newTotal, $order->total);

        $order = $orderQuery->findOne(1);
        $this->assertEquals($newTotal, $order->total);

        /** @see https://github.com/yiisoft/yii2/issues/12143 */
        $newOrder = new Order($this->db);
        $this->assertTrue($newOrder->getIsNewRecord());

        $newTotal = 200;
        $this->assertSame(0, $newOrder->updateAttributes(['total' => $newTotal]));
        $this->assertTrue($newOrder->getIsNewRecord());
        $this->assertEquals($newTotal, $newOrder->total);
    }

    /**
     * Ensure no ambiguous column error occurs if ActiveQuery adds a JOIN.
     *
     * {@see https://github.com/yiisoft/yii2/issues/13757}
     */
    public function testAmbiguousColumnFindOne(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customerQuery = new CustomerQuery(Customer::class, $this->db);

        $customerQuery->joinWithProfile = true;

        $arClass = $customerQuery->findOne(1);

        $this->assertTrue($arClass->refresh());

        $customerQuery->joinWithProfile = false;
    }

    public function testCustomARRelation(): void
    {
        $this->checkFixture($this->db, 'order_item');

        $orderItem = new ActiveQuery(OrderItem::class, $this->db);

        $orderItem = $orderItem->findOne(1);

        $this->assertInstanceOf(Order::class, $orderItem->custom);
    }

    public function testGetAttributes(): void
    {
        $attributesExpected = [];
        $this->checkFixture($this->db, 'customer');

        $attributesExpected['id'] = 1;
        $attributesExpected['email'] = 'user1@example.com';
        $attributesExpected['name'] = 'user1';
        $attributesExpected['address'] = 'address1';
        $attributesExpected['status'] = 1;

        if ($this->db->getDriverName() === 'pgsql') {
            $attributesExpected['bool_status'] = true;
        }

        if ($this->db->getDriverName() === 'oci') {
            $attributesExpected['bool_status'] = '1';
        }

        $attributesExpected['profile_id'] = 1;

        $customer = new ActiveQuery(Customer::class, $this->db);

        $attributes = $customer->findOne(1)->getAttributes();

        $this->assertEquals($attributes, $attributesExpected);
    }

    public function testGetAttributesOnly(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customer = new ActiveQuery(Customer::class, $this->db);

        $attributes = $customer->findOne(1)->getAttributes(['id', 'email', 'name']);

        $this->assertEquals(['id' => 1, 'email' => 'user1@example.com', 'name' => 'user1'], $attributes);
    }

    public function testGetAttributesExcept(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customer = new ActiveQuery(Customer::class, $this->db);

        $attributes = $customer->findOne(1)->getAttributes(null, ['status', 'bool_status', 'profile_id']);

        $this->assertEquals(
            $attributes,
            ['id' => 1, 'email' => 'user1@example.com', 'name' => 'user1', 'address' => 'address1']
        );
    }

    public function testFields(): void
    {
        $this->checkFixture($this->db, 'order_item');

        $orderItem = new ActiveQuery(OrderItem::class, $this->db);

        $fields = $orderItem->findOne(['order_id' => 1, 'item_id' => 2])->fields();

        $this->assertEquals(
            $fields,
            ['order_id' => 1, 'item_id' => 2, 'quantity' => 2, 'subtotal' => '40', 'price' => 20]
        );
    }

    public function testGetOldAttribute(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customer = new ActiveQuery(Customer::class, $this->db);

        $query = $customer->findOne(1);
        $this->assertEquals('user1', $query->getOldAttribute('name'));
        $this->assertEquals($query->getAttributes(), $query->getOldAttributes());

        $query->setAttribute('name', 'samdark');
        $this->assertEquals('samdark', $query->getAttribute('name'));
        $this->assertEquals('user1', $query->getOldAttribute('name'));
        $this->assertNotEquals($query->getAttribute('name'), $query->getOldAttribute('name'));
    }

    public function testGetOldAttributes(): void
    {
        $attributes = [];
        $attributesNew = [];
        $this->checkFixture($this->db, 'customer');

        $attributes['id'] = 1;
        $attributes['email'] = 'user1@example.com';
        $attributes['name'] = 'user1';
        $attributes['address'] = 'address1';
        $attributes['status'] = 1;

        if ($this->db->getDriverName() === 'pgsql') {
            $attributes['bool_status'] = true;
        }

        if ($this->db->getDriverName() === 'oci') {
            $attributes['bool_status'] = '1';
        }

        $attributes['profile_id'] = 1;

        $customer = new ActiveQuery(Customer::class, $this->db);

        $query = $customer->findOne(1);
        $this->assertEquals($query->getAttributes(), $attributes);
        $this->assertEquals($query->getAttributes(), $query->getOldAttributes());

        $query->setAttribute('name', 'samdark');
        $attributesNew['id'] = 1;
        $attributesNew['email'] = 'user1@example.com';
        $attributesNew['name'] = 'samdark';
        $attributesNew['address'] = 'address1';
        $attributesNew['status'] = 1;

        if ($this->db->getDriverName() === 'pgsql') {
            $attributesNew['bool_status'] = true;
        }

        if ($this->db->getDriverName() === 'oci') {
            $attributesNew['bool_status'] = '1';
        }

        $attributesNew['profile_id'] = 1;

        $this->assertEquals($attributesNew, $query->getAttributes());
        $this->assertEquals($attributes, $query->getOldAttributes());
        $this->assertNotEquals($query->getAttributes(), $query->getOldAttributes());
    }

    public function testIsAttributeChanged(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customer = new ActiveQuery(Customer::class, $this->db);

        $query = $customer->findOne(1);
        $this->assertEquals('user1', $query->getAttribute('name'));
        $this->assertEquals('user1', $query->getOldAttribute('name'));

        $query->setAttribute('name', 'samdark');
        $this->assertEquals('samdark', $query->getAttribute('name'));
        $this->assertEquals('user1', $query->getOldAttribute('name'));
        $this->assertNotEquals($query->getAttribute('name'), $query->getOldAttribute('name'));
        $this->assertTrue($query->isAttributeChanged('name', true));
    }

    public function testIsAttributeChangedNotIdentical(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customer = new ActiveQuery(Customer::class, $this->db);

        $query = $customer->findOne(1);
        $this->assertEquals('user1', $query->getAttribute('name'));
        $this->assertEquals('user1', $query->getOldAttribute('name'));

        $query->setAttribute('name', 'samdark');
        $this->assertEquals('samdark', $query->getAttribute('name'));
        $this->assertEquals('user1', $query->getOldAttribute('name'));
        $this->assertNotEquals($query->getAttribute('name'), $query->getOldAttribute('name'));
        $this->assertTrue($query->isAttributeChanged('name', false));
    }

    public function testOldAttributeAfterInsertAndUpdate(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customer = new Customer($this->db);

        $customer->setAttributes([
            'email' => 'info@example.com',
            'name' => 'Jack',
            'address' => '123 Ocean Dr',
            'status' => 1,
        ]);

        $this->assertNull($customer->getOldAttribute('name'));
        $this->assertTrue($customer->save());
        $this->assertSame('Jack', $customer->getOldAttribute('name'));

        $customer->setAttribute('name', 'Harry');

        $this->assertTrue($customer->save());
        $this->assertSame('Harry', $customer->getOldAttribute('name'));
    }

    public function testCheckRelationUnknownPropertyException(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customer = new ActiveQuery(Customer::class, $this->db);

        $query = $customer->findOne(1);

        $this->expectException(UnknownPropertyException::class);
        $this->expectExceptionMessage('Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer::noExist');
        $query->noExist;
    }

    public function testCheckRelationInvalidCallException(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customer = new ActiveQuery(Customer::class, $this->db);

        $query = $customer->findOne(2);

        $this->expectException(InvalidCallException::class);
        $this->expectExceptionMessage(
            'Getting write-only property: Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer::ordersReadOnly'
        );
        $query->ordersReadOnly;
    }

    public function testGetRelationInvalidArgumentException(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customer = new ActiveQuery(Customer::class, $this->db);

        $query = $customer->findOne(1);

        /** Without throwing exception */
        $this->assertEmpty($query->relationQuery('items', false));

        /** Throwing exception */
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer has no relation named "items".'
        );
        $query->relationQuery('items');
    }

    public function testGetRelationInvalidArgumentExceptionHasNoRelationNamed(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customer = new ActiveQuery(Customer::class, $this->db);

        $query = $customer->findOne(1);

        /** Without throwing exception */
        $this->assertEmpty($query->relationQuery('item', false));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Relation query method "Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer::getItemQuery()" should'
            . ' return type "Yiisoft\ActiveRecord\ActiveQueryInterface", but  returns "void" type.'
        );
        $query->relationQuery('item');
    }

    public function testGetRelationInvalidArgumentExceptionCaseSensitive(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customer = new ActiveQuery(Customer::class, $this->db);

        $query = $customer->findOne(1);

        $this->assertEmpty($query->relationQuery('expensiveorders', false));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Relation names are case sensitive. Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer ' .
            'has a relation named "expensiveOrders" instead of "expensiveorders"'
        );
        $query->relationQuery('expensiveorders');
    }

    public function testExists(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customer = new ActiveQuery(Customer::class, $this->db);

        $this->assertTrue($customer->where(['id' => 2])->exists());
        $this->assertFalse($customer->where(['id' => 5])->exists());
        $this->assertTrue($customer->where(['name' => 'user1'])->exists());
        $this->assertFalse($customer->where(['name' => 'user5'])->exists());
        $this->assertTrue($customer->where(['id' => [2, 3]])->exists());
        $this->assertTrue($customer->where(['id' => [2, 3]])->offset(1)->exists());
        $this->assertFalse($customer->where(['id' => [2, 3]])->offset(2)->exists());
    }

    public function testUnlink(): void
    {
        $this->checkFixture($this->db, 'customer');

        /** has many without delete */
        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customer = $customerQuery->findOne(2);
        $this->assertCount(2, $customer->ordersWithNullFK);
        $customer->unlink('ordersWithNullFK', $customer->ordersWithNullFK[1], false);
        $this->assertCount(1, $customer->ordersWithNullFK);

        $orderWithNullFKQuery = new ActiveQuery(OrderWithNullFK::class, $this->db);
        $orderWithNullFK = $orderWithNullFKQuery->findOne(3);
        $this->assertEquals(3, $orderWithNullFK->id);
        $this->assertNull($orderWithNullFK->customer_id);

        /** has many with delete */
        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customer = $customerQuery->findOne(2);
        $this->assertCount(2, $customer->orders);

        $customer->unlink('orders', $customer->orders[1], true);
        $this->assertCount(1, $customer->orders);

        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $this->assertNull($orderQuery->findOne(3));

        /** via model with delete */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $order = $orderQuery->findOne(2);
        $this->assertCount(3, $order->items);
        $this->assertCount(3, $order->orderItems);
        $order->unlink('items', $order->items[2], true);
        $this->assertCount(2, $order->items);
        $this->assertCount(2, $order->orderItems);

        /** via model without delete */
        $this->assertCount(2, $order->itemsWithNullFK);
        $order->unlink('itemsWithNullFK', $order->itemsWithNullFK[1], false);

        $this->assertCount(1, $order->itemsWithNullFK);
        $this->assertCount(2, $order->orderItems);
    }

    public function testUnlinkAllAndConditionSetNull(): void
    {
        $this->checkFixture($this->db, 'order_item_with_null_fk');

        /** in this test all orders are owned by customer 1 */
        $orderWithNullFKInstance = new OrderWithNullFK($this->db);
        $orderWithNullFKInstance->updateAll(['customer_id' => 1]);

        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customer = $customerQuery->findOne(1);
        $this->assertCount(3, $customer->ordersWithNullFK);
        $this->assertCount(1, $customer->expensiveOrdersWithNullFK);

        $orderWithNullFKQuery = new ActiveQuery(OrderWithNullFK::class, $this->db);
        $this->assertEquals(3, $orderWithNullFKQuery->count());

        $customer->unlinkAll('expensiveOrdersWithNullFK');
        $this->assertCount(3, $customer->ordersWithNullFK);
        $this->assertCount(0, $customer->expensiveOrdersWithNullFK);
        $this->assertEquals(3, $orderWithNullFKQuery->count());

        $customer = $customerQuery->findOne(1);
        $this->assertCount(2, $customer->ordersWithNullFK);
        $this->assertCount(0, $customer->expensiveOrdersWithNullFK);
    }

    public function testUnlinkAllAndConditionDelete(): void
    {
        $this->checkFixture($this->db, 'customer', true);

        /** in this test all orders are owned by customer 1 */
        $orderInstance = new Order($this->db);
        $orderInstance->updateAll(['customer_id' => 1]);

        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customer = $customerQuery->findOne(1);
        $this->assertCount(3, $customer->orders);
        $this->assertCount(1, $customer->expensiveOrders);

        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $this->assertEquals(3, $orderQuery->count());

        $customer->unlinkAll('expensiveOrders', true);
        $this->assertCount(3, $customer->orders);
        $this->assertCount(0, $customer->expensiveOrders);
        $this->assertEquals(2, $orderQuery->count());

        $customer = $customerQuery->findOne(1);
        $this->assertCount(2, $customer->orders);
        $this->assertCount(0, $customer->expensiveOrders);
    }

    public function testUpdate(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customer = $customerQuery->findOne(2);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('user2', $customer->getAttribute('name'));
        $this->assertFalse($customer->getIsNewRecord());
        $this->assertEmpty($customer->getDirtyAttributes());

        $customer->setAttribute('name', 'user2x');
        $customer->save();
        $this->assertEquals('user2x', $customer->getAttribute('name'));
        $this->assertFalse($customer->getIsNewRecord());

        $customer2 = $customerQuery->findOne(2);
        $this->assertEquals('user2x', $customer2->getAttribute('name'));

        /** no update */
        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customer = $customerQuery->findOne(1);

        $customer->setAttribute('name', 'user1');
        $this->assertEquals(0, $customer->update());

        /** updateAll */
        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customer = $customerQuery->findOne(3);
        $this->assertEquals('user3', $customer->getAttribute('name'));

        $ret = $customer->updateAll(['name' => 'temp'], ['id' => 3]);
        $this->assertEquals(1, $ret);

        $customer = $customerQuery->findOne(3);
        $this->assertEquals('temp', $customer->getAttribute('name'));

        $ret = $customer->updateAll(['name' => 'tempX']);
        $this->assertEquals(3, $ret);

        $ret = $customer->updateAll(['name' => 'temp'], ['name' => 'user6']);
        $this->assertEquals(0, $ret);
    }

    public function testUpdateCounters(): void
    {
        $this->checkFixture($this->db, 'order_item', true);

        /** updateCounters */
        $pk = ['order_id' => 2, 'item_id' => 4];
        $orderItemQuery = new ActiveQuery(OrderItem::class, $this->db);
        $orderItem = $orderItemQuery->findOne($pk);
        $this->assertEquals(1, $orderItem->quantity);

        $ret = $orderItem->updateCounters(['quantity' => -1]);
        $this->assertTrue($ret);
        $this->assertEquals(0, $orderItem->quantity);

        $orderItem = $orderItemQuery->findOne($pk);
        $this->assertEquals(0, $orderItem->quantity);

        /** updateAllCounters */
        $pk = ['order_id' => 1, 'item_id' => 2];
        $orderItemQuery = new ActiveQuery(OrderItem::class, $this->db);
        $orderItem = $orderItemQuery->findOne($pk);
        $this->assertEquals(2, $orderItem->quantity);

        $orderItem = new OrderItem($this->db);
        $ret = $orderItem->updateAllCounters(['quantity' => 3, 'subtotal' => -10], $pk);
        $this->assertEquals(1, $ret);

        $orderItem = $orderItemQuery->findOne($pk);
        $this->assertEquals(5, $orderItem->quantity);
        $this->assertEquals(30, $orderItem->subtotal);
    }

    public function testDelete(): void
    {
        $this->checkFixture($this->db, 'customer', true);

        /** delete */
        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customer = $customerQuery->findOne(2);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('user2', $customer->name);

        $customer->delete();

        $customer = $customerQuery->findOne(2);
        $this->assertNull($customer);

        /** deleteAll */
        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customers = $customerQuery->all();
        $this->assertCount(2, $customers);

        $customer = new Customer($this->db);
        $ret = $customer->deleteAll();
        $this->assertEquals(2, $ret);

        $customers = $customerQuery->all();
        $this->assertCount(0, $customers);

        $ret = $customer->deleteAll();
        $this->assertEquals(0, $ret);
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/17089}
     */
    public function testViaWithCallable(): void
    {
        $this->checkFixture($this->db, 'order', true);

        $orderQuery = new ActiveQuery(Order::class, $this->db);

        $order = $orderQuery->findOne(2);

        $expensiveItems = $order->expensiveItemsUsingViaWithCallable;
        $cheapItems = $order->cheapItemsUsingViaWithCallable;

        $this->assertCount(2, $expensiveItems);
        $this->assertEquals(4, $expensiveItems[0]->id);
        $this->assertEquals(5, $expensiveItems[1]->id);
        $this->assertCount(1, $cheapItems);
        $this->assertEquals(3, $cheapItems[0]->id);
    }

    public function testLink(): void
    {
        $this->checkFixture($this->db, 'customer', true);

        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customer = $customerQuery->findOne(2);
        $this->assertCount(2, $customer->orders);

        /** has many */
        $order = new Order($this->db);

        $order->total = 100;
        $order->created_at = time();
        $this->assertTrue($order->isNewRecord);

        /** belongs to */
        $order = new Order($this->db);

        $order->total = 100;
        $order->created_at = time();
        $this->assertTrue($order->isNewRecord);

        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customer = $customerQuery->findOne(1);
        $this->assertNull($order->customer);

        $order->link('customer', $customer);
        $this->assertFalse($order->isNewRecord);
        $this->assertEquals(1, $order->customer_id);
        $this->assertEquals(1, $order->customer->primaryKey);

        /** via model */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $order = $orderQuery->findOne(1);
        $this->assertCount(2, $order->items);
        $this->assertCount(2, $order->orderItems);

        $orderItemQuery = new ActiveQuery(OrderItem::class, $this->db);
        $orderItem = $orderItemQuery->findOne(['order_id' => 1, 'item_id' => 3]);
        $this->assertNull($orderItem);

        $itemQuery = new ActiveQuery(Item::class, $this->db);
        $item = $itemQuery->findOne(3);
        $order->link('items', $item, ['quantity' => 10, 'subtotal' => 100]);
        $this->assertCount(3, $order->items);
        $this->assertCount(3, $order->orderItems);

        $orderItemQuery = new ActiveQuery(OrderItem::class, $this->db);
        $orderItem = $orderItemQuery->findOne(['order_id' => 1, 'item_id' => 3]);
        $this->assertInstanceOf(OrderItem::class, $orderItem);
        $this->assertEquals(10, $orderItem->quantity);
        $this->assertEquals(100, $orderItem->subtotal);
    }

    public function testEqual(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customerA = (new ActiveQuery(Customer::class, $this->db))->findOne(1);
        $customerB = (new ActiveQuery(Customer::class, $this->db))->findOne(2);
        $this->assertFalse($customerA->equals($customerB));

        $customerB = (new ActiveQuery(Customer::class, $this->db))->findOne(1);
        $this->assertTrue($customerA->equals($customerB));

        $customerA = (new ActiveQuery(Customer::class, $this->db))->findOne(1);
        $customerB = (new ActiveQuery(Item::class, $this->db))->findOne(1);
        $this->assertFalse($customerA->equals($customerB));
    }
}
