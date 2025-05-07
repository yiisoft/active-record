<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Throwable;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ArArrayHelper;
use Yiisoft\ActiveRecord\OptimisticLockException;
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
use Yiisoft\Db\Exception\UnknownPropertyException;
use Yiisoft\Db\Query\QueryInterface;

use function sort;
use function ucfirst;

abstract class ActiveQueryTest extends TestCase
{
    public function testOptions(): void
    {
        $this->checkFixture($this->db(), 'customer', true);

        $customerQuery = new ActiveQuery(Customer::class);

        $query = $customerQuery->on(['a' => 'b'])->joinWith('profile');
        $this->assertEquals(Customer::class, $query->getARClass());
        $this->assertEquals(['a' => 'b'], $query->getOn());
        $this->assertEquals([[['profile'], true, 'LEFT JOIN']], $query->getJoinWith());
        $customerQuery->resetJoinWith();
        $this->assertEquals([], $query->getJoinWith());
    }

    public function testPrepare(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $query = new ActiveQuery(Customer::class);
        $this->assertInstanceOf(QueryInterface::class, $query->prepare($this->db()->getQueryBuilder()));
    }

    public function testPopulateEmptyRows(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $query = new ActiveQuery(Customer::class);
        $this->assertEquals([], $query->populate([]));
    }

    public function testPopulateFilledRows(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $query = new ActiveQuery(Customer::class);
        $rows = $query->all();
        $result = $query->populate($rows);
        $this->assertEquals($rows, $result);
    }

    public function testAll(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $query = new ActiveQuery(Customer::class);

        foreach ($query->all() as $customer) {
            $this->assertInstanceOf(Customer::class, $customer);
        }

        $this->assertCount(3, $query->all());
    }

    public function testOne(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $query = new ActiveQuery(Customer::class);
        $this->assertInstanceOf(Customer::class, $query->one());
    }

    public function testCreateCommand(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $query = new ActiveQuery(Customer::class);
        $this->assertInstanceOf(AbstractCommand::class, $query->createCommand());
    }

    public function testQueryScalar(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $query = new ActiveQuery(Customer::class);
        $this->assertEquals('user1', Assert::invokeMethod($query, 'queryScalar', ['name']));
    }

    public function testGetJoinWith(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $query = new ActiveQuery(Customer::class);
        $query->joinWith('profile');
        $this->assertEquals([[['profile'], true, 'LEFT JOIN']], $query->getJoinWith());
    }

    public function testInnerJoinWith(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $query = new ActiveQuery(Customer::class);
        $query->innerJoinWith('profile');
        $this->assertEquals([[['profile'], true, 'INNER JOIN']], $query->getJoinWith());
    }

    public function testBuildJoinWithRemoveDuplicateJoinByTableName(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $query = new ActiveQuery(Customer::class);
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
        $this->checkFixture($this->db(), 'customer');

        $query = new ActiveQuery(Customer::class);
        $this->assertEquals(['customer', 'customer'], Assert::invokeMethod($query, 'getTableNameAndAlias'));
    }

    public function testGetQueryTableNameFromSet(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $query = new ActiveQuery(Customer::class);
        $query->from(['alias' => 'customer']);
        $this->assertEquals(['customer', 'alias'], Assert::invokeMethod($query, 'getTableNameAndAlias'));
    }

    public function testOnCondition(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = new ActiveQuery(Customer::class);
        $query->onCondition($on, $params);
        $this->assertEquals($on, $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testAndOnConditionOnNotSet(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $on = ['active' => true];
        $params = ['a' => 'b'];
        $query = new ActiveQuery(Customer::class);
        $query->andOnCondition($on, $params);
        $this->assertEquals($on, $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testAndOnConditionOnSet(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $onOld = ['active' => true];
        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = new ActiveQuery(Customer::class);

        $query->on($onOld)->andOnCondition($on, $params);

        $this->assertEquals(['and', $onOld, $on], $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testOrOnConditionOnNotSet(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = new ActiveQuery(Customer::class);

        $query->orOnCondition($on, $params);

        $this->assertEquals($on, $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testOrOnConditionOnSet(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $onOld = ['active' => true];
        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = new ActiveQuery(Customer::class);

        $query->on($onOld)->orOnCondition($on, $params);

        $this->assertEquals(['or', $onOld, $on], $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testViaWithEmptyPrimaryModel(): void
    {
        $query = new ActiveQuery(Customer::class);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Setting via is only supported for relational queries.');

        $query->via('profile');
    }

    public function testViaTable(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $order = new Order();

        $query = new ActiveQuery(Customer::class);

        $query->primaryModel($order)->viaTable(Profile::class, ['id' => 'item_id']);

        $this->assertInstanceOf(ActiveQuery::class, $query);
        $this->assertInstanceOf(ActiveQuery::class, $query->getVia());
    }

    public function testAliasNotSet(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $query = new ActiveQuery(Customer::class);

        $query->alias('alias');

        $this->assertInstanceOf(ActiveQuery::class, $query);
        $this->assertEquals(['alias' => 'customer'], $query->getFrom());
    }

    public function testAliasYetSet(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $aliasOld = ['old'];

        $query = new ActiveQuery(Customer::class);

        $query->from($aliasOld)->alias('alias');

        $this->assertInstanceOf(ActiveQuery::class, $query);
        $this->assertEquals(['alias' => 'old'], $query->getFrom());
    }

    public function testGetTableNamesNotFilledFrom(): void
    {
        $this->checkFixture($this->db(), 'profile');

        $query = new ActiveQuery(Profile::class);
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
        $this->checkFixture($this->db(), 'profile');

        $query = new ActiveQuery(Profile::class);

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
        $this->checkFixture($this->db(), 'category', true);

        /** @var $category Category */
        $categoriesQuery = new ActiveQuery(Category::class);

        $categories = $categoriesQuery->with('orders')->indexBy('id')->all();
        $category = $categories[1];
        $this->assertNotNull($category);

        $orders = $category->getOrders();
        $this->assertCount(2, $orders);
        $this->assertInstanceOf(Order::class, $orders[0]);
        $this->assertInstanceOf(Order::class, $orders[1]);

        $ids = [$orders[0]->getId(), $orders[1]->getId()];
        sort($ids);
        $this->assertEquals([1, 3], $ids);

        $category = $categories[2];
        $this->assertNotNull($category);

        $orders = $category->getOrders();
        $this->assertCount(1, $orders);
        $this->assertInstanceOf(Order::class, $orders[0]);
        $this->assertEquals(2, $orders[0]->getId());
    }

    public function testGetSql(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $query = new ActiveQuery(Customer::class);

        $query->sql('SELECT * FROM {{customer}} ORDER BY [[id]] DESC');

        $this->assertEquals('SELECT * FROM {{customer}} ORDER BY [[id]] DESC', $query->getSql());
    }

    public function testCustomColumns(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerQuery = new ActiveQuery(Customer::class);

        /** find custom column */
        if ($this->db()->getDriverName() === 'oci') {
            $customers = $customerQuery
                ->select(['{{customer}}.*', '([[status]]*2) AS [[status2]]'])
                ->where(['name' => 'user3'])->one();
        } else {
            $customers = $customerQuery
                ->select(['*', '([[status]]*2) AS [[status2]]'])
                ->where(['name' => 'user3'])->one();
        }

        $this->assertEquals(3, $customers->get('id'));
        $this->assertEquals(4, $customers->status2);
    }

    public function testCallFind(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerQuery = new ActiveQuery(Customer::class);

        /** find count, sum, average, min, max, scalar */
        $this->assertEquals(3, $customerQuery->count());
        $this->assertEquals(6, $customerQuery->sum('[[id]]'));
        $this->assertEquals(2, $customerQuery->average('[[id]]'));
        $this->assertEquals(1, $customerQuery->min('[[id]]'));
        $this->assertEquals(3, $customerQuery->max('[[id]]'));
        $this->assertEquals(3, $customerQuery->select('COUNT(*)')->scalar());
        $this->assertEquals(2, $customerQuery->where('[[id]]=1 OR [[id]]=2')->count());
    }

    public function testDeeplyNestedTableRelation(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerQuery = new ActiveQuery(Customer::class);

        $customers = $customerQuery->findByPk(1);

        $items = $customers->getOrderItems();

        $this->assertCount(2, $items);
        $this->assertEquals(1, $items[0]->get('id'));
        $this->assertEquals(2, $items[1]->get('id'));
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
        $this->checkFixture($this->db(), 'category');

        $categoryQuery = new ActiveQuery(Category::class);

        $categories = $categoryQuery->where(['id' => 1])->one();
        $this->assertNotNull($categories);

        $orders = $categories->getOrders();
        $this->assertCount(2, $orders);
        $this->assertInstanceOf(Order::class, $orders[0]);
        $this->assertInstanceOf(Order::class, $orders[1]);

        $ids = [$orders[0]->getId(), $orders[1]->get('id')];
        sort($ids);
        $this->assertEquals([1, 3], $ids);

        $categories = $categoryQuery->setWhere(['id' => 2])->one();
        $this->assertNotNull($categories);

        $orders = $categories->getOrders();
        $this->assertCount(1, $orders);
        $this->assertEquals(2, $orders[0]->get('id'));
        $this->assertInstanceOf(Order::class, $orders[0]);
    }

    public function testJoinWith(): void
    {
        $this->checkFixture($this->db(), 'order');

        /** left join and eager loading */
        $orderQuery = new ActiveQuery(Order::class);
        $orders = $orderQuery->joinWith('customer')->orderBy('customer.id DESC, order.id')->all();
        $this->assertCount(3, $orders);
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertEquals(3, $orders[1]->getId());
        $this->assertEquals(1, $orders[2]->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));
        $this->assertTrue($orders[2]->isRelationPopulated('customer'));

        /** inner join filtering and eager loading */
        $orderQuery = new ActiveQuery(Order::class);
        $orders = $orderQuery->innerJoinWith(
            [
                'customer' => function ($query) {
                    $query->where('{{customer}}.[[id]]=2');
                },
            ]
        )->orderBy('order.id')->all();
        $this->assertCount(2, $orders);
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertEquals(3, $orders[1]->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));

        /** inner join filtering, eager loading, conditions on both primary and relation */
        $orderQuery = new ActiveQuery(Order::class);
        $orders = $orderQuery->innerJoinWith(
            [
                'customer' => function ($query) {
                    $query->where(['customer.id' => 2]);
                },
            ]
        )->where(['order.id' => [1, 2]])->orderBy('order.id')->all();
        $this->assertCount(1, $orders);
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));

        /** inner join filtering without eager loading */
        $orderQuery = new ActiveQuery(Order::class);
        $orders = $orderQuery->innerJoinWith(
            [
                'customer' => static function ($query) {
                    $query->where('{{customer}}.[[id]]=2');
                },
            ],
            false
        )->orderBy('order.id')->all();
        $this->assertCount(2, $orders);
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertEquals(3, $orders[1]->getId());
        $this->assertFalse($orders[0]->isRelationPopulated('customer'));
        $this->assertFalse($orders[1]->isRelationPopulated('customer'));

        /** inner join filtering without eager loading, conditions on both primary and relation */
        $orderQuery = new ActiveQuery(Order::class);
        $orders = $orderQuery->innerJoinWith(
            [
                'customer' => static function ($query) {
                    $query->where(['customer.id' => 2]);
                },
            ],
            false
        )->where(['order.id' => [1, 2]])->orderBy('order.id')->all();
        $this->assertCount(1, $orders);
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertFalse($orders[0]->isRelationPopulated('customer'));

        /** join with via-relation */
        $orderQuery = new ActiveQuery(Order::class);
        $orders = $orderQuery->innerJoinWith('books')->orderBy('order.id')->all();
        $this->assertCount(2, $orders);
        $this->assertCount(2, $orders[0]->getBooks());
        $this->assertCount(1, $orders[1]->getBooks());
        $this->assertEquals(1, $orders[0]->getId());
        $this->assertEquals(3, $orders[1]->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('books'));
        $this->assertTrue($orders[1]->isRelationPopulated('books'));

        /** join with sub-relation */
        $orderQuery = new ActiveQuery(Order::class);
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
        $this->assertCount(3, $orders[0]->getItems());
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertEquals(2, $orders[0]->getItems()[0]->getCategory()->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $this->assertTrue($orders[0]->getItems()[0]->isRelationPopulated('category'));

        /** join with table alias */
        $orderQuery = new ActiveQuery(Order::class);
        $orders = $orderQuery->joinWith(
            [
                'customer' => function ($q) {
                    $q->from('customer c');
                },
            ]
        )->orderBy('c.id DESC, order.id')->all();
        $this->assertCount(3, $orders);
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertEquals(3, $orders[1]->getId());
        $this->assertEquals(1, $orders[2]->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));
        $this->assertTrue($orders[2]->isRelationPopulated('customer'));

        /** join with table alias */
        $orderQuery = new ActiveQuery(Order::class);
        $orders = $orderQuery->joinWith('customer as c')->orderBy('c.id DESC, order.id')->all();
        $this->assertCount(3, $orders);
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertEquals(3, $orders[1]->getId());
        $this->assertEquals(1, $orders[2]->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));
        $this->assertTrue($orders[2]->isRelationPopulated('customer'));

        /** join with table alias sub-relation */
        $orderQuery = new ActiveQuery(Order::class);
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
        $this->assertCount(3, $orders[0]->getItems());
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertEquals(2, $orders[0]->getItems()[0]->getCategory()->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $this->assertTrue($orders[0]->getItems()[0]->isRelationPopulated('category'));

        /** join with ON condition */
        $orderQuery = new ActiveQuery(Order::class);
        $orders = $orderQuery->joinWith('books2')->orderBy('order.id')->all();
        $this->assertCount(3, $orders);
        $this->assertCount(2, $orders[0]->getBooks2());
        $this->assertCount(0, $orders[1]->getBooks2());
        $this->assertCount(1, $orders[2]->getBooks2());
        $this->assertEquals(1, $orders[0]->getId());
        $this->assertEquals(2, $orders[1]->getId());
        $this->assertEquals(3, $orders[2]->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('books2'));
        $this->assertTrue($orders[1]->isRelationPopulated('books2'));
        $this->assertTrue($orders[2]->isRelationPopulated('books2'));

        /** lazy loading with ON condition */
        $orderQuery = new ActiveQuery(Order::class);
        $order = $orderQuery->findByPk(1);
        $this->assertCount(2, $order->getBooks2());

        $orderQuery = new ActiveQuery(Order::class);
        $order = $orderQuery->findByPk(2);
        $this->assertCount(0, $order->getBooks2());

        $order = new ActiveQuery(Order::class);
        $order = $order->findByPk(3);
        $this->assertCount(1, $order->getBooks2());

        /** eager loading with ON condition */
        $orderQuery = new ActiveQuery(Order::class);
        $orders = $orderQuery->with('books2')->all();
        $this->assertCount(3, $orders);
        $this->assertCount(2, $orders[0]->getBooks2());
        $this->assertCount(0, $orders[1]->getBooks2());
        $this->assertCount(1, $orders[2]->getBooks2());
        $this->assertEquals(1, $orders[0]->getId());
        $this->assertEquals(2, $orders[1]->getId());
        $this->assertEquals(3, $orders[2]->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('books2'));
        $this->assertTrue($orders[1]->isRelationPopulated('books2'));
        $this->assertTrue($orders[2]->isRelationPopulated('books2'));

        /** join with count and query */
        $orderQuery = new ActiveQuery(Order::class);
        $query = $orderQuery->joinWith('customer');
        $count = $query->count();
        $this->assertEquals(3, $count);

        $orders = $query->all();
        $this->assertCount(3, $orders);

        /** {@see https://github.com/yiisoft/yii2/issues/2880} */
        $orderQuery = new ActiveQuery(Order::class);
        $query = $orderQuery->findByPk(1);
        $customer = $query->getCustomerQuery()->joinWith(
            [
                'orders' => static function ($q) {
                    $q->orderBy([]);
                },
            ]
        )->one();
        $this->assertEquals(1, $customer->getId());

        $orderQuery = new ActiveQuery(Order::class);
        $order = $orderQuery->joinWith(
            [
                'items' => static function ($q) {
                    $q->from(['items' => 'item'])->orderBy('items.id');
                },
            ]
        )->orderBy('order.id')->one();

        /** join with sub-relation called inside Closure */
        $orderQuery = new ActiveQuery(Order::class);
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
        $this->assertCount(3, $orders[0]->getItems());
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertEquals(2, $orders[0]->getItems()[0]->getCategory()->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $this->assertTrue($orders[0]->getItems()[0]->isRelationPopulated('category'));
    }

    /**
     * @depends testJoinWith
     */
    public function testJoinWithAndScope(): void
    {
        $this->checkFixture($this->db(), 'customer');

        /**  hasOne inner join */
        $customer = new CustomerQuery(Customer::class);
        $customers = $customer->active()->innerJoinWith('profile')->orderBy('customer.id')->all();
        $this->assertCount(1, $customers);
        $this->assertEquals(1, $customers[0]->getId());
        $this->assertTrue($customers[0]->isRelationPopulated('profile'));

        /** hasOne outer join */
        $customer = new CustomerQuery(Customer::class);
        $customers = $customer->active()->joinWith('profile')->orderBy('customer.id')->all();
        $this->assertCount(2, $customers);
        $this->assertEquals(1, $customers[0]->getId());
        $this->assertEquals(2, $customers[1]->getId());
        $this->assertInstanceOf(Profile::class, $customers[0]->getProfile());
        $this->assertNull($customers[1]->getProfile());
        $this->assertTrue($customers[0]->isRelationPopulated('profile'));
        $this->assertTrue($customers[1]->isRelationPopulated('profile'));

        /** hasMany */
        $customer = new CustomerQuery(Customer::class);
        $customers = $customer->active()->joinWith(
            [
                'orders' => static function ($q) {
                    $q->orderBy('order.id');
                },
            ]
        )->orderBy('customer.id DESC, order.id')->all();
        $this->assertCount(2, $customers);
        $this->assertEquals(2, $customers[0]->getId());
        $this->assertEquals(1, $customers[1]->getId());
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
        $this->checkFixture($this->db(), 'order');

        $orderQuery = new ActiveQuery(Order::class);

        $this->db()->getQueryBuilder()->setSeparator("\n");

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
        $this->checkFixture($this->db(), 'order');

        /** left join and eager loading */
        $orderQuery = new ActiveQuery(Order::class);
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
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertEquals(3, $orders[1]->getId());
        $this->assertEquals(1, $orders[2]->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));
        $this->assertTrue($orders[2]->isRelationPopulated('customer'));

        /** inner join filtering and eager loading */
        $orderQuery = new ActiveQuery(Order::class);
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
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertEquals(3, $orders[1]->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));

        /** inner join filtering without eager loading */
        $orderQuery = new ActiveQuery(Order::class);
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
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertEquals(3, $orders[1]->getId());
        $this->assertFalse($orders[0]->isRelationPopulated('customer'));
        $this->assertFalse($orders[1]->isRelationPopulated('customer'));

        /** join with via-relation */
        $orderQuery = new ActiveQuery(Order::class);
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
        $this->assertCount(2, $orders[0]->getBooks());
        $this->assertCount(1, $orders[1]->getBooks());
        $this->assertEquals(1, $orders[0]->getId());
        $this->assertEquals(3, $orders[1]->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('books'));
        $this->assertTrue($orders[1]->isRelationPopulated('books'));

        /** joining sub relations */
        $orderQuery = new ActiveQuery(Order::class);
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
        $this->assertCount(3, $orders[0]->getItems());
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertEquals(2, $orders[0]->getItems()[0]->getCategory()->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $this->assertTrue($orders[0]->getItems()[0]->isRelationPopulated('category'));

        /** join with ON condition */
        if ($aliasMethod === 'explicit' || $aliasMethod === 'querysyntax') {
            $relationName = 'books' . ucfirst($aliasMethod);

            $orderQuery = new ActiveQuery(Order::class);
            $orders = $orderQuery->joinWith(["$relationName b"])->orderBy('order.id')->all();

            $this->assertCount(3, $orders);
            $this->assertCount(2, $orders[0]->relation($relationName));
            $this->assertCount(0, $orders[1]->relation($relationName));
            $this->assertCount(1, $orders[2]->relation($relationName));
            $this->assertEquals(1, $orders[0]->getId());
            $this->assertEquals(2, $orders[1]->getId());
            $this->assertEquals(3, $orders[2]->getId());
            $this->assertTrue($orders[0]->isRelationPopulated($relationName));
            $this->assertTrue($orders[1]->isRelationPopulated($relationName));
            $this->assertTrue($orders[2]->isRelationPopulated($relationName));
        }

        /** join with ON condition and alias in relation definition */
        if ($aliasMethod === 'explicit' || $aliasMethod === 'querysyntax') {
            $relationName = 'books' . ucfirst($aliasMethod) . 'A';

            $orderQuery = new ActiveQuery(Order::class);
            $orders = $orderQuery->joinWith([$relationName])->orderBy('order.id')->all();

            $this->assertCount(3, $orders);
            $this->assertCount(2, $orders[0]->relation($relationName));
            $this->assertCount(0, $orders[1]->relation($relationName));
            $this->assertCount(1, $orders[2]->relation($relationName));
            $this->assertEquals(1, $orders[0]->getId());
            $this->assertEquals(2, $orders[1]->getId());
            $this->assertEquals(3, $orders[2]->getId());
            $this->assertTrue($orders[0]->isRelationPopulated($relationName));
            $this->assertTrue($orders[1]->isRelationPopulated($relationName));
            $this->assertTrue($orders[2]->isRelationPopulated($relationName));
        }

        /** join with count and query */
        $orderQuery = new ActiveQuery(Order::class);
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
        $orderQuery = new ActiveQuery(Order::class);
        $order = $orderQuery->findByPk(1);

        $customerQuery = $order->getCustomerQuery()->innerJoinWith(['orders o'], false);

        if ($aliasMethod === 'explicit') {
            $customer = $customerQuery->where(['o.id' => 1])->one();
        } elseif ($aliasMethod === 'querysyntax') {
            $customer = $customerQuery->where(['{{@order}}.id' => 1])->one();
        } elseif ($aliasMethod === 'applyAlias') {
            $customer = $customerQuery->where([$query->applyAlias('order', 'id') => 1])->one();
        }

        $this->assertEquals(1, $customer->getId());
        $this->assertNotNull($customer);

        /** join with sub-relation called inside Closure */
        $orderQuery = new ActiveQuery(Order::class);
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
        $this->assertCount(3, $orders[0]->getItems());
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertEquals(2, $orders[0]->getItems()[0]->getCategory()->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $this->assertTrue($orders[0]->getItems()[0]->isRelationPopulated('category'));
    }

    /**
     * @depends testJoinWith
     */
    public function testJoinWithSameTable(): void
    {
        $this->checkFixture($this->db(), 'order');

        /**
         * join with the same table but different aliases alias is defined in the relation definition without eager
         * loading
         */
        $query = new ActiveQuery(Order::class);
        $query->joinWith('bookItems', false)->joinWith('movieItems', false)->where(['movies.name' => 'Toy Story']);
        $orders = $query->all();
        $this->assertCount(
            1,
            $orders,
            $query->createCommand()->getRawSql() . print_r($orders, true)
        );
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertFalse($orders[0]->isRelationPopulated('bookItems'));
        $this->assertFalse($orders[0]->isRelationPopulated('movieItems'));

        /** with eager loading */
        $query = new ActiveQuery(Order::class);
        $query->joinWith('bookItems', true)->joinWith('movieItems', true)->where(['movies.name' => 'Toy Story']);
        $orders = $query->all();
        $this->assertCount(
            1,
            $orders,
            $query->createCommand()->getRawSql() . print_r($orders, true)
        );
        $this->assertCount(0, $orders[0]->getBookItems());
        $this->assertCount(3, $orders[0]->getMovieItems());
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('bookItems'));
        $this->assertTrue($orders[0]->isRelationPopulated('movieItems'));

        /**
         * join with the same table but different aliases alias is defined in the call to joinWith() without eager
         * loading
         */
        $query = new ActiveQuery(Order::class);
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
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertFalse($orders[0]->isRelationPopulated('itemsIndexed'));

        /** with eager loading, only for one relation as it would be overwritten otherwise. */
        $query = new ActiveQuery(Order::class);
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
        $this->assertCount(3, $orders[0]->getItemsIndexed());
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('itemsIndexed'));

        /** with eager loading, and the other relation */
        $query = new ActiveQuery(Order::class);
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
        $this->assertCount(0, $orders[0]->getItemsIndexed());
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('itemsIndexed'));
    }

    /**
     * @depends testJoinWith
     */
    public function testJoinWithDuplicateSimple(): void
    {
        $this->checkFixture($this->db(), 'order');

        /** left join and eager loading */
        $orderQuery = new ActiveQuery(Order::class);

        $orders = $orderQuery
            ->innerJoinWith('customer')
            ->joinWith('customer')
            ->orderBy('customer.id DESC, order.id')
            ->all();

        $this->assertCount(3, $orders);
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertEquals(3, $orders[1]->getId());
        $this->assertEquals(1, $orders[2]->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));
        $this->assertTrue($orders[2]->isRelationPopulated('customer'));
    }

    /**
     * @depends testJoinWith
     */
    public function testJoinWithDuplicateCallbackFiltering(): void
    {
        $this->checkFixture($this->db(), 'order');

        /** inner join filtering and eager loading */
        $orderQuery = new ActiveQuery(Order::class);

        $orders = $orderQuery
            ->innerJoinWith('customer')
            ->joinWith([
                'customer' => function ($query) {
                    $query->where('{{customer}}.[[id]]=2');
                },
            ])->orderBy('order.id')->all();

        $this->assertCount(2, $orders);
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertEquals(3, $orders[1]->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));
    }

    /**
     * @depends testJoinWith
     */
    public function testJoinWithDuplicateCallbackFilteringConditionsOnPrimary(): void
    {
        $this->checkFixture($this->db(), 'order');

        /** inner join filtering, eager loading, conditions on both primary and relation */
        $orderQuery = new ActiveQuery(Order::class);

        $orders = $orderQuery
            ->innerJoinWith('customer')
            ->joinWith([
                'customer' => function ($query) {
                    $query->where(['{{customer}}.[[id]]' => 2]);
                },
            ])->where(['order.id' => [1, 2]])->orderBy('order.id')->all();

        $this->assertCount(1, $orders);
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
    }

    /**
     * @depends testJoinWith
     */
    public function testJoinWithDuplicateWithSubRelation(): void
    {
        $this->checkFixture($this->db(), 'order');

        /** join with sub-relation */
        $orderQuery = new ActiveQuery(Order::class);

        $orders = $orderQuery
            ->innerJoinWith('items')
            ->joinWith([
                'items.category' => function ($q) {
                    $q->where('{{category}}.[[id]] = 2');
                },
            ])->orderBy('order.id')->all();

        $this->assertCount(1, $orders);
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertCount(3, $orders[0]->getItems());
        $this->assertTrue($orders[0]->getItems()[0]->isRelationPopulated('category'));
        $this->assertEquals(2, $orders[0]->getItems()[0]->getCategory()->getId());
    }

    /**
     * @depends testJoinWith
     */
    public function testJoinWithDuplicateTableAlias1(): void
    {
        $this->checkFixture($this->db(), 'order');

        /** join with table alias */
        $orderQuery = new ActiveQuery(Order::class);

        $orders = $orderQuery
            ->innerJoinWith('customer')
            ->joinWith([
                'customer' => function ($q) {
                    $q->from('customer c');
                },
            ])->orderBy('c.id DESC, order.id')->all();

        $this->assertCount(3, $orders);
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertEquals(3, $orders[1]->getId());
        $this->assertEquals(1, $orders[2]->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));
        $this->assertTrue($orders[2]->isRelationPopulated('customer'));
    }

    /**
     * @depends testJoinWith
     */
    public function testJoinWithDuplicateTableAlias2(): void
    {
        $this->checkFixture($this->db(), 'order');

        /** join with table alias */
        $orderQuery = new ActiveQuery(Order::class);

        $orders = $orderQuery
            ->innerJoinWith('customer')
            ->joinWith('customer as c')
            ->orderBy('c.id DESC, order.id')
            ->all();

        $this->assertCount(3, $orders);
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertEquals(3, $orders[1]->getId());
        $this->assertEquals(1, $orders[2]->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));
        $this->assertTrue($orders[2]->isRelationPopulated('customer'));
    }

    /**
     * @depends testJoinWith
     */
    public function testJoinWithDuplicateTableAliasSubRelation(): void
    {
        $this->checkFixture($this->db(), 'order');

        /** join with table alias sub-relation */
        $orderQuery = new ActiveQuery(Order::class);

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
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertCount(3, $orders[0]->getItems());
        $this->assertTrue($orders[0]->getItems()[0]->isRelationPopulated('category'));
        $this->assertEquals(2, $orders[0]->getItems()[0]->getCategory()->getId());
    }

    /**
     * @depends testJoinWith
     */
    public function testJoinWithDuplicateSubRelationCalledInsideClosure(): void
    {
        $this->checkFixture($this->db(), 'order');

        /** join with sub-relation called inside Closure */
        $orderQuery = new ActiveQuery(Order::class);

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
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertCount(3, $orders[0]->getItems());
        $this->assertTrue($orders[0]->getItems()[0]->isRelationPopulated('category'));
        $this->assertEquals(2, $orders[0]->getItems()[0]->getCategory()->getId());
    }

    public function testAlias(): void
    {
        $this->checkFixture($this->db(), 'order');

        $order = new Order();

        $query = new ActiveQuery(Order::class);
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
        $this->checkFixture($this->db(), 'customer');

        /** eager loading: find one and all */
        $customerQuery = new ActiveQuery(Customer::class);
        $customer = $customerQuery->with('orders2')->where(['id' => 1])->one();
        $this->assertSame($customer->getOrders2()[0]->getCustomer2(), $customer);

        $customers = $customerQuery->with('orders2')->setWhere(['id' => [1, 3]])->all();
        $this->assertEmpty($customers[1]->getOrders2());
        $this->assertSame($customers[0]->getOrders2()[0]->getCustomer2(), $customers[0]);

        /** lazy loading */
        $customerQuery = new ActiveQuery(Customer::class);
        $customer = $customerQuery->findByPk(2);
        $orders = $customer->getOrders2();
        $this->assertCount(2, $orders);
        $this->assertSame($customer->getOrders2()[0]->getCustomer2(), $customer);
        $this->assertSame($customer->getOrders2()[1]->getCustomer2(), $customer);

        /** ad-hoc lazy loading */
        $customerQuery = new ActiveQuery(Customer::class);
        $customer = $customerQuery->findByPk(2);
        $orders = $customer->getOrders2Query()->all();
        $this->assertCount(2, $orders);
        $this->assertSame($orders[0]->getCustomer2(), $customer);
        $this->assertSame($orders[1]->getCustomer2(), $customer);
        $this->assertTrue(
            $orders[0]->isRelationPopulated('customer2'),
            'inverse relation did not populate the relation'
        );
        $this->assertTrue(
            $orders[1]->isRelationPopulated('customer2'),
            'inverse relation did not populate the relation'
        );

        /** the other way around */
        $customerQuery = new ActiveQuery(Customer::class);
        $customer = $customerQuery->with('orders2')->where(['id' => 1])->asArray()->one();
        $this->assertSame($customer['orders2'][0]['customer2']['id'], $customer['id']);

        $customerQuery = new ActiveQuery(Customer::class);
        $customers = $customerQuery->with('orders2')->where(['id' => [1, 3]])->asArray()->all();
        $this->assertSame($customer['orders2'][0]['customer2']['id'], $customers[0]['id']);
        $this->assertEmpty($customers[1]['orders2']);

        $orderQuery = new ActiveQuery(Order::class);
        $orders = $orderQuery->with('customer2')->where(['id' => 1])->all();
        $this->assertSame($orders[0]->getCustomer2()->getOrders2(), [$orders[0]]);

        $orderQuery = new ActiveQuery(Order::class);
        $order = $orderQuery->with('customer2')->where(['id' => 1])->one();
        $this->assertSame($order->getCustomer2()->getOrders2(), [$order]);

        $orderQuery = new ActiveQuery(Order::class);
        $orders = $orderQuery->with('customer2')->where(['id' => 1])->asArray()->all();
        $this->assertSame($orders[0]['customer2']['orders2'][0]['id'], $orders[0]['id']);

        $orderQuery = new ActiveQuery(Order::class);
        $order = $orderQuery->with('customer2')->where(['id' => 1])->asArray()->one();
        $this->assertSame($order['customer2']['orders2'][0]['id'], $orders[0]['id']);

        $orderQuery = new ActiveQuery(Order::class);
        $orders = $orderQuery->with('customer2')->where(['id' => [1, 3]])->all();
        $this->assertSame($orders[0]->getCustomer2()->getOrders2(), [$orders[0]]);
        $this->assertSame($orders[1]->getCustomer2()->getOrders2(), [$orders[1]]);

        $orderQuery = new ActiveQuery(Order::class);
        $orders = $orderQuery->with('customer2')->where(['id' => [2, 3]])->orderBy('id')->all();
        $this->assertSame($orders[0]->getCustomer2()->getOrders2(), $orders);
        $this->assertSame($orders[1]->getCustomer2()->getOrders2(), $orders);

        $orderQuery = new ActiveQuery(Order::class);
        $orders = $orderQuery->with('customer2')->where(['id' => [2, 3]])->orderBy('id')->asArray()->all();
        $this->assertSame($orders[0]['customer2']['orders2'][0]['id'], $orders[0]['id']);
        $this->assertSame($orders[0]['customer2']['orders2'][1]['id'], $orders[1]['id']);
        $this->assertSame($orders[1]['customer2']['orders2'][0]['id'], $orders[0]['id']);
        $this->assertSame($orders[1]['customer2']['orders2'][1]['id'], $orders[1]['id']);
    }

    public function testUnlinkAllViaTable(): void
    {
        $this->checkFixture($this->db(), 'order', true);

        /** via table with delete. */
        $orderQuery = new ActiveQuery(Order::class);
        $order = $orderQuery->findByPk(1);
        $this->assertCount(2, $order->getBooksViaTable());

        $orderItemQuery = new ActiveQuery(OrderItem::class);
        $orderItemCount = $orderItemQuery->count();

        $itemQuery = new ActiveQuery(Item::class);
        $this->assertEquals(5, $itemQuery->count());

        $order->unlinkAll('booksViaTable', true);
        $this->assertEquals(5, $itemQuery->count());
        $this->assertEquals($orderItemCount - 2, $orderItemQuery->count());
        $this->assertCount(0, $order->getBooksViaTable());

        /** via table without delete */
        $this->assertCount(2, $order->getBooksWithNullFKViaTable());

        $orderItemsWithNullFKQuery = new ActiveQuery(OrderItemWithNullFK::class);
        $orderItemCount = $orderItemsWithNullFKQuery->count();
        $this->assertEquals(5, $itemQuery->count());

        $order->unlinkAll('booksWithNullFKViaTable', false);
        $this->assertCount(0, $order->getBooksWithNullFKViaTable());
        $this->assertEquals(2, $orderItemsWithNullFKQuery->where(
            ['AND', ['item_id' => [1, 2]], ['order_id' => null]]
        )->count());

        $orderItemsWithNullFKQuery = new ActiveQuery(OrderItemWithNullFK::class);
        $this->assertEquals($orderItemCount, $orderItemsWithNullFKQuery->count());
        $this->assertEquals(5, $itemQuery->count());
    }

    public function testIssues(): void
    {
        $this->checkFixture($this->db(), 'category', true);

        /** {@see https://github.com/yiisoft/yii2/issues/4938} */
        $categoryQuery = new ActiveQuery(Category::class);
        $category = $categoryQuery->findByPk(2);
        $this->assertInstanceOf(Category::class, $category);
        $this->assertEquals(3, $category->getItemsQuery()->count());
        $this->assertEquals(1, $category->getLimitedItemsQuery()->count());
        $this->assertEquals(1, $category->getLimitedItemsQuery()->distinct(true)->count());

        /** {@see https://github.com/yiisoft/yii2/issues/3197} */
        $orderQuery = new ActiveQuery(Order::class);
        $orders = $orderQuery->with('orderItems')->orderBy('id')->all();
        $this->assertCount(3, $orders);
        $this->assertCount(2, $orders[0]->getOrderItems());
        $this->assertCount(3, $orders[1]->getOrderItems());
        $this->assertCount(1, $orders[2]->getOrderItems());

        $orderQuery = new ActiveQuery(Order::class);
        $orders = $orderQuery->with(
            [
                'orderItems' => static function ($q) {
                    $q->indexBy('item_id');
                },
            ]
        )->orderBy('id')->all();
        $this->assertCount(3, $orders);
        $this->assertCount(2, $orders[0]->getOrderItems());
        $this->assertCount(3, $orders[1]->getOrderItems());
        $this->assertCount(1, $orders[2]->getOrderItems());

        /** {@see https://github.com/yiisoft/yii2/issues/8149} */
        $arClass = new Customer();

        $arClass->setName('test');
        $arClass->setEmail('test');
        $arClass->save();

        $arClass->updateCounters(['status' => 1]);
        $this->assertEquals(1, $arClass->getStatus());
    }

    public function testPopulateWithoutPk(): void
    {
        $this->checkFixture($this->db(), 'customer', true);

        /** tests with single pk asArray */
        $customerQuery = new ActiveQuery(Customer::class);
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
        $customerQuery = new ActiveQuery(Customer::class);
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
        $customerQuery = new ActiveQuery(Customer::class);
        $aggregation = $customerQuery
            ->select(['{{customer}}.[[status]]', 'SUM({{order}}.[[total]]) AS [[sumTotal]]'])
            ->joinWith('ordersPlain', false)
            ->groupBy('{{customer}}.[[status]]')
            ->orderBy('status')
            ->all();

        $this->assertCount(2, $aggregation);
        $this->assertContainsOnlyInstancesOf(Customer::class, $aggregation);

        foreach ($aggregation as $item) {
            if ($item->getStatus() === 1) {
                $this->assertEquals(183, $item->sumTotal);
            } elseif ($item->getStatus() === 2) {
                $this->assertEquals(0, $item->sumTotal);
            }
        }

        /** tests with composite pk asArray */
        $orderItemQuery = new ActiveQuery(OrderItem::class);
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
        $orderItemQuery = new ActiveQuery(OrderItem::class);
        $aggregation = $orderItemQuery
            ->select(['[[order_id]]', 'SUM([[subtotal]]) AS [[subtotal]]'])
            ->joinWith('order', false)
            ->groupBy('[[order_id]]')
            ->orderBy('[[order_id]]')
            ->all();

        $this->assertCount(3, $aggregation);
        $this->assertContainsOnlyInstancesOf(OrderItem::class, $aggregation);

        foreach ($aggregation as $item) {
            if ($item->getOrderId() === 1) {
                $this->assertEquals(70, $item->getSubtotal());
            } elseif ($item->getOrderId() === 2) {
                $this->assertEquals(33, $item->getSubtotal());
            } elseif ($item->getOrderId() === 3) {
                $this->assertEquals(40, $item->getSubtotal());
            }
        }
    }

    public function testLinkWhenRelationIsIndexed2(): void
    {
        $this->checkFixture($this->db(), 'order');

        $orderQuery = new ActiveQuery(Order::class);
        $order = $orderQuery->with('orderItems2')->where(['id' => 1])->one();

        $orderItem = new OrderItem();

        $orderItem->setOrderId($order->getId());
        $orderItem->setItemId(3);
        $orderItem->setQuantity(1);
        $orderItem->setSubtotal(10.0);

        $order->link('orderItems2', $orderItem);
        $this->assertTrue(isset($order->getOrderItems2()['3']));
    }

    public function testEmulateExecution(): void
    {
        $this->checkFixture($this->db(), 'order');

        $customerQuery = new ActiveQuery(Customer::class);

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
        $this->checkFixture($this->db(), 'item');

        /** Ensure there are three items with category_id = 2 in the Items table */
        $itemQuery = new ActiveQuery(Item::class);
        $itemsCount = $itemQuery->where(['category_id' => 2])->count();
        $this->assertEquals(3, $itemsCount);

        $categoryQuery = new ActiveQuery(Category::class);
        $categoryQuery = $categoryQuery->with('limitedItems')->where(['id' => 2]);

        /**
         * Ensure that limitedItems relation returns only one item (category_id = 2 and id in (1,2,3))
         */
        $category = $categoryQuery->one();
        $this->assertCount(1, $category->getLimitedItems());

        /** Unlink all items in the limitedItems relation */
        $category->unlinkAll('limitedItems', true);

        /** Make sure that only one item was unlinked */
        $itemsCount = $itemQuery->setWhere(['category_id' => 2])->count();
        $this->assertEquals(2, $itemsCount);

        /** Call $categoryQuery again to ensure no items were found */
        $this->assertCount(0, $categoryQuery->one()->getLimitedItems());
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/12213}
     */
    public function testUnlinkAllOnConditionViaTable(): void
    {
        $this->checkFixture($this->db(), 'item', true);

        /** Ensure there are three items with category_id = 2 in the Items table */
        $itemQuery = new ActiveQuery(Item::class);
        $itemsCount = $itemQuery->where(['category_id' => 2])->count();
        $this->assertEquals(3, $itemsCount);

        $orderQuery = new ActiveQuery(Order::class);
        $orderQuery = $orderQuery->with('limitedItems')->where(['id' => 2]);

        /**
         * Ensure that limitedItems relation returns only one item (category_id = 2 and id in (4, 5)).
         */
        $category = $orderQuery->one();
        $this->assertCount(2, $category->getLimitedItems());

        /** Unlink all items in the limitedItems relation */
        $category->unlinkAll('limitedItems', true);

        /** Call $orderQuery again to ensure that links are removed */
        $this->assertCount(0, $orderQuery->one()->getLimitedItems());

        /** Make sure that only links were removed, the items were not removed */
        $this->assertEquals(3, $itemQuery->setWhere(['category_id' => 2])->count());
    }

    /**
     * {@see https://github.com/yiisoft/yii2/pull/13891}
     */
    public function testIndexByAfterLoadingRelations(): void
    {
        $this->checkFixture($this->db(), 'order');

        $orderQuery = new ActiveQuery(Order::class);
        $orderQuery->with('customer')->indexBy(function (Order $order) {
            $this->assertTrue($order->isRelationPopulated('customer'));
            $this->assertNotEmpty($order->getCustomer()?->getId());

            return $order->getCustomer()?->getId();
        })->all();

        $orders = $orderQuery->with('customer')->indexBy('customer.id')->all();

        foreach ($orders as $customer_id => $order) {
            $this->assertEquals($customer_id, $order->getCustomerId());
        }
    }

    public function testExtraFields(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerQuery = new ActiveQuery(Customer::class);

        $query = $customerQuery->with('orders2')->where(['id' => 1])->one();
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
        $driverName = $this->db()->getDriverName();

        $this->checkFixture($this->db(), 'order');

        $order = new Order();
        $orderItem = new OrderItem();

        $this->assertSame('order', $order->getTableName());
        $this->assertSame('order_item', $orderItem->getTableName());

        $order = $order->withTableName($orderTableName);
        $orderItem = $orderItem->withTableName($orderItemTableName);

        $this->assertSame($orderTableName, $order->getTableName());
        $this->assertSame($orderItemTableName, $orderItem->getTableName());

        $orderQuery = new ActiveQuery(Order::class);
        $order = $orderQuery->findByPk(1);
        $itemsSQL = $order->getOrderItemsQuery()->createCommand()->getRawSql();
        $expectedSQL = DbHelper::replaceQuotes(
            <<<SQL
            SELECT * FROM [[order_item]] WHERE [[order_id]]=1
            SQL,
            $driverName,
        );
        $this->assertEquals($expectedSQL, $itemsSQL);

        $order = $orderQuery->findByPk(1);
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
        $this->checkFixture($this->db(), 'order_item', true);

        $orderItemQuery = new ActiveQuery(OrderItem::class);
        $orderItems = $orderItemQuery->findByPk([1, 1]);
        $this->assertEquals(1, $orderItems->getOrder()->getId());
        $this->assertEquals(1, $orderItems->getItem()->getId());

        /** test `__set()`. */
        $orderItems->setOrderId(2);
        $orderItems->setItemId(1);
        $this->assertEquals(2, $orderItems->getOrder()->getId());
        $this->assertEquals(1, $orderItems->getItem()->getId());

        /** Test `set()`. */
        $orderItems->set('order_id', 3);
        $orderItems->set('item_id', 1);
        $this->assertEquals(3, $orderItems->getOrder()->getId());
        $this->assertEquals(1, $orderItems->getItem()->getId());
    }

    public function testOutdatedCompositeKeyRelationsAreReset(): void
    {
        $this->checkFixture($this->db(), 'dossier');

        $dossierQuery = new ActiveQuery(Dossier::class);

        $dossiers = $dossierQuery->where(['department_id' => 1, 'employee_id' => 1])->one();
        $this->assertEquals('John Doe', $dossiers->getEmployee()->getFullName());

        $dossiers->setDepartmentId(2);
        $this->assertEquals('Ann Smith', $dossiers->getEmployee()->getFullName());

        $dossiers->setEmployeeId(2);
        $this->assertEquals('Will Smith', $dossiers->getEmployee()->getFullName());

        // Dossier::$employee_id property cannot be null
        // unset($dossiers->employee_id);
        // $this->assertNull($dossiers->getEmployee());

        $dossier = new Dossier();
        $this->assertNull($dossier->getEmployee());

        $dossier->setEmployeeId(1);
        $dossier->setDepartmentId(2);
        $this->assertEquals('Ann Smith', $dossier->getEmployee()->getFullName());

        $dossier->setEmployeeId(2);
        $this->assertEquals('Will Smith', $dossier->getEmployee()->getFullName());
    }

    public function testOutdatedViaTableRelationsAreReset(): void
    {
        $this->checkFixture($this->db(), 'order', true);

        $orderQuery = new ActiveQuery(Order::class);

        $orders = $orderQuery->findByPk(1);
        $orderItemIds = ArArrayHelper::getColumn($orders->getItems(), 'id');
        sort($orderItemIds);
        $this->assertSame([1, 2], $orderItemIds);

        $orders->setId(2);
        sort($orderItemIds);
        $orderItemIds = ArArrayHelper::getColumn($orders->getItems(), 'id');
        $this->assertSame([3, 4, 5], $orderItemIds);

        $orders->setId(null);
        $this->assertSame([], $orders->getItems());

        $order = new Order();
        $this->assertSame([], $order->getItems());

        $order->setId(3);
        $orderItemIds = ArArrayHelper::getColumn($order->getItems(), 'id');
        $this->assertSame([2], $orderItemIds);
    }

    public function testInverseOfDynamic(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerQuery = new ActiveQuery(Customer::class);

        $customer = $customerQuery->findByPk(1);

        /** request the inverseOf relation without explicitly (eagerly) loading it */
        $orders2 = $customer->getOrders2Query()->all();
        $this->assertSame($customer, $orders2[0]->getCustomer2());

        $orders2 = $customer->getOrders2Query()->one();
        $this->assertSame($customer, $orders2->getCustomer2());

        /**
         * request the inverseOf relation while also explicitly eager loading it (while possible, this is of course
         * redundant)
         */
        $orders2 = $customer->getOrders2Query()->with('customer2')->all();
        $this->assertSame($customer, $orders2[0]->getCustomer2());

        $orders2 = $customer->getOrders2Query()->with('customer2')->one();
        $this->assertSame($customer, $orders2->getCustomer2());

        /** request the inverseOf relation as array */
        $orders2 = $customer->getOrders2Query()->asArray()->all();
        $this->assertEquals($customer->getId(), $orders2[0]['customer2']->getId());

        $orders2 = $customer->getOrders2Query()->asArray()->one();
        $this->assertEquals($customer->getId(), $orders2['customer2']->getId());
    }

    public function testOptimisticLock(): void
    {
        $this->checkFixture($this->db(), 'document');

        $documentQuery = new ActiveQuery(Document::class);
        $record = $documentQuery->findByPk(1);

        $record->content = 'New Content';
        $record->save();
        $this->assertEquals(1, $record->version);

        $record = $documentQuery->findByPk(1);

        $record->content = 'Rewrite attempt content';
        $record->version = 0;
        $this->expectException(OptimisticLockException::class);
        $record->save();
    }

    public function testOptimisticLockOnDelete(): void
    {
        $this->checkFixture($this->db(), 'document', true);

        $documentQuery = new ActiveQuery(Document::class);
        $document = $documentQuery->findByPk(1);

        $this->assertSame(0, $document->version);

        $document->version = 1;

        $this->expectException(OptimisticLockException::class);
        $document->delete();
    }

    public function testOptimisticLockAfterDelete(): void
    {
        $this->checkFixture($this->db(), 'document', true);

        $documentQuery = new ActiveQuery(Document::class);
        $document = $documentQuery->findByPk(1);

        $this->assertSame(0, $document->version);
        $this->assertSame(1, $document->delete());
        $this->assertTrue($document->getIsNewRecord());

        $this->expectException(OptimisticLockException::class);
        $document->delete();
    }

    /** @link https://github.com/yiisoft/yii2/issues/9006 */
    public function testBit(): void
    {
        $this->checkFixture($this->db(), 'bit_values');

        $bitValueQuery = new ActiveQuery(BitValues::class);
        $falseBit = $bitValueQuery->findByPk(1);
        $this->assertFalse($falseBit->val);

        $bitValueQuery = new ActiveQuery(BitValues::class);
        $trueBit = $bitValueQuery->findByPk(2);
        $this->assertTrue($trueBit->val);
    }

    public function testUpdateProperties(): void
    {
        $this->checkFixture($this->db(), 'order');

        $orderQuery = new ActiveQuery(Order::class);
        $order = $orderQuery->findByPk(1);
        $newTotal = 978;
        $this->assertSame(1, $order->updateProperties(['total' => $newTotal]));
        $this->assertEquals($newTotal, $order->getTotal());

        $order = $orderQuery->findByPk(1);
        $this->assertEquals($newTotal, $order->getTotal());

        /** @see https://github.com/yiisoft/yii2/issues/12143 */
        $newOrder = new Order();
        $this->assertTrue($newOrder->getIsNewRecord());

        $newTotal = 200;
        $this->assertSame(0, $newOrder->updateProperties(['total' => $newTotal]));
        $this->assertTrue($newOrder->getIsNewRecord());
        $this->assertEquals($newTotal, $newOrder->getTotal());
    }

    /**
     * Ensure no ambiguous column error occurs if ActiveQuery adds a JOIN.
     *
     * {@see https://github.com/yiisoft/yii2/issues/13757}
     */
    public function testAmbiguousColumnFindOne(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerQuery = new CustomerQuery(Customer::class);

        $customerQuery->joinWithProfile = true;

        $arClass = $customerQuery->findByPk(1);

        $this->assertTrue($arClass->refresh());

        $customerQuery->joinWithProfile = false;
    }

    public function testCustomARRelation(): void
    {
        $this->checkFixture($this->db(), 'order_item');

        $orderItem = new ActiveQuery(OrderItem::class);

        $orderItem = $orderItem->findByPk([1, 1]);

        $this->assertInstanceOf(Order::class, $orderItem->getCustom());
    }

    public function testPropertyValues(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $expectedValues = [
            'id' => 1,
            'email' => 'user1@example.com',
            'name' => 'user1',
            'address' => 'address1',
            'status' => 1,
            'bool_status' => true,
            'profile_id' => 1,
        ];

        $customer = new ActiveQuery(Customer::class);

        $values = $customer->findByPk(1)->propertyValues();

        $this->assertEquals($expectedValues, $values);
    }

    public function testPropertyValuesOnly(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customer = new ActiveQuery(Customer::class);

        $values = $customer->findByPk(1)->propertyValues(['id', 'email', 'name']);

        $this->assertEquals(['id' => 1, 'email' => 'user1@example.com', 'name' => 'user1'], $values);
    }

    public function testPropertyValuesExcept(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customer = new ActiveQuery(Customer::class);

        $values = $customer->findByPk(1)->propertyValues(null, ['status', 'bool_status', 'profile_id']);

        $this->assertEquals(
            ['id' => 1, 'email' => 'user1@example.com', 'name' => 'user1', 'address' => 'address1'],
            $values
        );
    }

    public function testGetOldValue(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customer = new ActiveQuery(Customer::class);

        $query = $customer->findByPk(1);
        $this->assertEquals('user1', $query->oldValue('name'));
        $this->assertEquals($query->propertyValues(), $query->oldValues());

        $query->set('name', 'samdark');
        $this->assertEquals('samdark', $query->get('name'));
        $this->assertEquals('user1', $query->oldValue('name'));
        $this->assertNotEquals($query->get('name'), $query->oldValue('name'));
    }

    public function testGetOldValues(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $expectedValues = [
            'id' => 1,
            'email' => 'user1@example.com',
            'name' => 'user1',
            'address' => 'address1',
            'status' => 1,
            'bool_status' => true,
            'profile_id' => 1,
        ];

        $customer = new ActiveQuery(Customer::class);

        $query = $customer->findByPk(1);
        $this->assertEquals($expectedValues, $query->propertyValues());
        $this->assertEquals($query->propertyValues(), $query->oldValues());

        $query->set('name', 'samdark');

        $expectedNewValues = $expectedValues;
        $expectedNewValues['name'] = 'samdark';

        $this->assertEquals($expectedNewValues, $query->propertyValues());
        $this->assertEquals($expectedValues, $query->oldValues());
        $this->assertNotEquals($query->propertyValues(), $query->oldValues());
    }

    public function testIsPropertyChanged(): void
    {
        $this->checkFixture($this->db(), 'customer', true);

        $query = new ActiveQuery(Customer::class);

        $customer = $query->findByPk(1);
        $this->assertTrue($customer->get('bool_status'));
        $this->assertTrue($customer->oldValue('bool_status'));

        $customer->set('bool_status', 1);

        $this->assertTrue($customer->isPropertyChanged('bool_status'));
        $this->assertFalse($customer->isPropertyChangedNonStrict('bool_status'));

        $customer->set('bool_status', 0);

        $this->assertTrue($customer->isPropertyChanged('bool_status'));
        $this->assertTrue($customer->isPropertyChangedNonStrict('bool_status'));
    }

    public function testOldPropertyAfterInsertAndUpdate(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customer = new Customer();

        $customer->populateProperties([
            'email' => 'info@example.com',
            'name' => 'Jack',
            'address' => '123 Ocean Dr',
            'status' => 1,
        ]);

        $this->assertNull($customer->oldValue('name'));
        $this->assertTrue($customer->save());
        $this->assertSame('Jack', $customer->oldValue('name'));

        $customer->set('name', 'Harry');

        $this->assertTrue($customer->save());
        $this->assertSame('Harry', $customer->oldValue('name'));
    }

    public function testCheckRelationUnknownPropertyException(): void
    {
        self::markTestSkipped('There is no check for access to an unknown property.');

        $this->checkFixture($this->db(), 'customer');

        $customer = new ActiveQuery(Customer::class);

        $query = $customer->findByPk(1);

        $this->expectException(UnknownPropertyException::class);
        $this->expectExceptionMessage('Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer::noExist');
        $query->noExist;
    }

    public function testCheckRelationInvalidCallException(): void
    {
        self::markTestSkipped('There is no check for access to an unknown property.');

        $this->checkFixture($this->db(), 'customer');

        $customer = new ActiveQuery(Customer::class);

        $query = $customer->findByPk(2);

        $this->expectException(InvalidCallException::class);
        $this->expectExceptionMessage(
            'Getting write-only property: Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer::ordersReadOnly'
        );
        $query->ordersReadOnly;
    }

    public function testGetRelationInvalidArgumentException(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customer = new ActiveQuery(Customer::class);

        $query = $customer->findByPk(1);

        /** Throwing exception */
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer has no relation named "items".'
        );
        $query->relationQuery('items');
    }

    public function testGetRelationInvalidArgumentExceptionHasNoRelationNamed(): void
    {
        self::markTestSkipped('The same as test testGetRelationInvalidArgumentException()');

        $this->checkFixture($this->db(), 'customer');

        $customer = new ActiveQuery(Customer::class);

        $query = $customer->findByPk(1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Relation query method "Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer::getItemQuery()" should'
            . ' return type "Yiisoft\ActiveRecord\ActiveQueryInterface", but  returns "void" type.'
        );
        $query->relationQuery('item');
    }

    public function testGetRelationInvalidArgumentExceptionCaseSensitive(): void
    {
        self::markTestSkipped('The same as test testGetRelationInvalidArgumentException()');

        $this->checkFixture($this->db(), 'customer');

        $customer = new ActiveQuery(Customer::class);

        $query = $customer->findByPk(1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Relation names are case sensitive. Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer ' .
            'has a relation named "expensiveOrders" instead of "expensiveorders"'
        );
        $query->relationQuery('expensiveorders');
    }

    public function testExists(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customer = new ActiveQuery(Customer::class);

        $this->assertTrue($customer->where(['id' => 2])->exists());
        $this->assertFalse($customer->setWhere(['id' => 5])->exists());
        $this->assertTrue($customer->setWhere(['name' => 'user1'])->exists());
        $this->assertFalse($customer->setWhere(['name' => 'user5'])->exists());
        $this->assertTrue($customer->setWhere(['id' => [2, 3]])->exists());
        $this->assertTrue($customer->setWhere(['id' => [2, 3]])->offset(1)->exists());
        $this->assertFalse($customer->setWhere(['id' => [2, 3]])->offset(2)->exists());
    }

    public function testUnlink(): void
    {
        $this->checkFixture($this->db(), 'customer');

        /** has many without delete */
        $customerQuery = new ActiveQuery(Customer::class);
        $customer = $customerQuery->findByPk(2);
        $this->assertCount(2, $customer->getOrdersWithNullFK());
        $customer->unlink('ordersWithNullFK', $customer->getOrdersWithNullFK()[1], false);
        $this->assertCount(1, $customer->getOrdersWithNullFK());

        $orderWithNullFKQuery = new ActiveQuery(OrderWithNullFK::class);
        $orderWithNullFK = $orderWithNullFKQuery->findByPk(3);
        $this->assertEquals(3, $orderWithNullFK->getId());
        $this->assertNull($orderWithNullFK->getCustomerId());

        /** has many with delete */
        $customerQuery = new ActiveQuery(Customer::class);
        $customer = $customerQuery->findByPk(2);
        $this->assertCount(2, $customer->getOrders());

        $customer->unlink('orders', $customer->getOrders()[1], true);
        $this->assertCount(1, $customer->getOrders());

        $orderQuery = new ActiveQuery(Order::class);
        $this->assertNull($orderQuery->findByPk(3));

        /** via model with delete */
        $orderQuery = new ActiveQuery(Order::class);
        $order = $orderQuery->findByPk(2);
        $this->assertCount(3, $order->getItems());
        $this->assertCount(3, $order->getOrderItems());
        $order->unlink('items', $order->getItems()[2], true);
        $this->assertCount(2, $order->getItems());
        $this->assertCount(2, $order->getOrderItems());

        /** via model without delete */
        $this->assertCount(2, $order->getItemsWithNullFK());
        $order->unlink('itemsWithNullFK', $order->getItemsWithNullFK()[1], false);

        $this->assertCount(1, $order->getItemsWithNullFK());
        $this->assertCount(2, $order->getOrderItems());
    }

    public function testUnlinkAllAndConditionSetNull(): void
    {
        $this->checkFixture($this->db(), 'order_item_with_null_fk');

        /** in this test all orders are owned by customer 1 */
        $orderWithNullFKInstance = new OrderWithNullFK();
        $orderWithNullFKInstance->updateAll(['customer_id' => 1]);

        $customerQuery = new ActiveQuery(Customer::class);
        $customer = $customerQuery->findByPk(1);
        $this->assertCount(3, $customer->getOrdersWithNullFK());
        $this->assertCount(1, $customer->getExpensiveOrdersWithNullFK());

        $orderWithNullFKQuery = new ActiveQuery(OrderWithNullFK::class);
        $this->assertEquals(3, $orderWithNullFKQuery->count());

        $customer->unlinkAll('expensiveOrdersWithNullFK');
        $this->assertCount(3, $customer->getOrdersWithNullFK());
        $this->assertCount(0, $customer->getExpensiveOrdersWithNullFK());
        $this->assertEquals(3, $orderWithNullFKQuery->count());

        $customer = $customerQuery->findByPk(1);
        $this->assertCount(2, $customer->getOrdersWithNullFK());
        $this->assertCount(0, $customer->getExpensiveOrdersWithNullFK());
    }

    public function testUnlinkAllAndConditionDelete(): void
    {
        $this->checkFixture($this->db(), 'customer', true);

        /** in this test all orders are owned by customer 1 */
        $orderInstance = new Order();
        $orderInstance->updateAll(['customer_id' => 1]);

        $customerQuery = new ActiveQuery(Customer::class);
        $customer = $customerQuery->findByPk(1);
        $this->assertCount(3, $customer->getOrders());
        $this->assertCount(1, $customer->getExpensiveOrders());

        $orderQuery = new ActiveQuery(Order::class);
        $this->assertEquals(3, $orderQuery->count());

        $customer->unlinkAll('expensiveOrders', true);
        $this->assertCount(3, $customer->getOrders());
        $this->assertCount(0, $customer->getExpensiveOrders());
        $this->assertEquals(2, $orderQuery->count());

        $customer = $customerQuery->findByPk(1);
        $this->assertCount(2, $customer->getOrders());
        $this->assertCount(0, $customer->getExpensiveOrders());
    }

    public function testUpdate(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerQuery = new ActiveQuery(Customer::class);
        $customer = $customerQuery->findByPk(2);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('user2', $customer->get('name'));
        $this->assertFalse($customer->getIsNewRecord());
        $this->assertEmpty($customer->newValues());

        $customer->set('name', 'user2x');
        $customer->save();
        $this->assertEquals('user2x', $customer->get('name'));
        $this->assertFalse($customer->getIsNewRecord());

        $customer2 = $customerQuery->findByPk(2);
        $this->assertEquals('user2x', $customer2->get('name'));

        /** no update */
        $customerQuery = new ActiveQuery(Customer::class);
        $customer = $customerQuery->findByPk(1);

        $customer->set('name', 'user1');
        $this->assertEquals(0, $customer->update());

        /** updateAll */
        $customerQuery = new ActiveQuery(Customer::class);
        $customer = $customerQuery->findByPk(3);
        $this->assertEquals('user3', $customer->get('name'));

        $ret = $customer->updateAll(['name' => 'temp'], ['id' => 3]);
        $this->assertEquals(1, $ret);

        $customer = $customerQuery->findByPk(3);
        $this->assertEquals('temp', $customer->get('name'));

        $ret = $customer->updateAll(['name' => 'tempX']);
        $this->assertEquals(3, $ret);

        $ret = $customer->updateAll(['name' => 'temp'], ['name' => 'user6']);
        $this->assertEquals(0, $ret);
    }

    public function testUpdateCounters(): void
    {
        $this->checkFixture($this->db(), 'order_item', true);

        /** updateCounters */
        $pk = [2, 4];
        $orderItemQuery = new ActiveQuery(OrderItem::class);
        $orderItem = $orderItemQuery->findByPk($pk);
        $this->assertEquals(1, $orderItem->getQuantity());

        $ret = $orderItem->updateCounters(['quantity' => -1]);
        $this->assertTrue($ret);
        $this->assertEquals(0, $orderItem->getQuantity());

        $orderItem = $orderItemQuery->findByPk($pk);
        $this->assertEquals(0, $orderItem->getQuantity());

        /** updateAllCounters */
        $pk = [1, 2];
        $orderItemQuery = new ActiveQuery(OrderItem::class);
        $orderItem = $orderItemQuery->findByPk($pk);
        $this->assertEquals(2, $orderItem->getQuantity());

        $orderItem = new OrderItem();
        $ret = $orderItem->updateAllCounters(['quantity' => 3, 'subtotal' => -10], ['order_id' => 1, 'item_id' => 2]);
        $this->assertEquals(1, $ret);

        $orderItem = $orderItemQuery->findByPk($pk);
        $this->assertEquals(5, $orderItem->getQuantity());
        $this->assertEquals(30, $orderItem->getSubtotal());
    }

    public function testDelete(): void
    {
        $this->checkFixture($this->db(), 'customer', true);

        /** delete */
        $customerQuery = new ActiveQuery(Customer::class);
        $customer = $customerQuery->findByPk(2);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('user2', $customer->getName());

        $customer->delete();

        $customer = $customerQuery->findByPk(2);
        $this->assertNull($customer);

        /** deleteAll */
        $customerQuery = new ActiveQuery(Customer::class);
        $customers = $customerQuery->all();
        $this->assertCount(2, $customers);

        $customer = new Customer();
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
        $this->checkFixture($this->db(), 'order', true);

        $orderQuery = new ActiveQuery(Order::class);

        $order = $orderQuery->findByPk(2);

        $expensiveItems = $order->getExpensiveItemsUsingViaWithCallable();
        $cheapItems = $order->getCheapItemsUsingViaWithCallable();

        $this->assertCount(2, $expensiveItems);
        $this->assertEquals(4, $expensiveItems[0]->getId());
        $this->assertEquals(5, $expensiveItems[1]->getId());
        $this->assertCount(1, $cheapItems);
        $this->assertEquals(3, $cheapItems[0]->getId());
    }

    public function testLink(): void
    {
        $this->checkFixture($this->db(), 'customer', true);

        $customerQuery = new ActiveQuery(Customer::class);
        $customer = $customerQuery->findByPk(2);
        $this->assertCount(2, $customer->getOrders());

        /** has many */
        $order = new Order();

        $order->setTotal(100);
        $order->setCreatedAt(time());
        $this->assertTrue($order->getIsNewRecord());

        /** belongs to */
        $order = new Order();

        $order->setTotal(100);
        $order->setCreatedAt(time());
        $this->assertTrue($order->getIsNewRecord());

        $customerQuery = new ActiveQuery(Customer::class);
        $customer = $customerQuery->findByPk(1);
        $this->assertNull($order->getCustomer());

        $order->link('customer', $customer);
        $this->assertFalse($order->getIsNewRecord());
        $this->assertEquals(1, $order->getCustomerId());
        $this->assertEquals(1, $order->getCustomer()->getPrimaryKey());

        /** via model */
        $orderQuery = new ActiveQuery(Order::class);
        $order = $orderQuery->findByPk(1);
        $this->assertCount(2, $order->getItems());
        $this->assertCount(2, $order->getOrderItems());

        $orderItemQuery = new ActiveQuery(OrderItem::class);
        $orderItem = $orderItemQuery->findByPk([1, 3]);
        $this->assertNull($orderItem);

        $itemQuery = new ActiveQuery(Item::class);
        $item = $itemQuery->findByPk(3);
        $order->link('items', $item, ['quantity' => 10, 'subtotal' => 100]);
        $this->assertCount(3, $order->getItems());
        $this->assertCount(3, $order->getOrderItems());

        $orderItemQuery = new ActiveQuery(OrderItem::class);
        $orderItem = $orderItemQuery->findByPk([1, 3]);
        $this->assertInstanceOf(OrderItem::class, $orderItem);
        $this->assertEquals(10, $orderItem->getQuantity());
        $this->assertEquals(100, $orderItem->getSubtotal());
    }

    public function testEqual(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerA = (new ActiveQuery(Customer::class))->findByPk(1);
        $customerB = (new ActiveQuery(Customer::class))->findByPk(2);
        $this->assertFalse($customerA->equals($customerB));

        $customerB = (new ActiveQuery(Customer::class))->findByPk(1);
        $this->assertTrue($customerA->equals($customerB));

        $customerA = (new ActiveQuery(Customer::class))->findByPk(1);
        $customerB = (new ActiveQuery(Item::class))->findByPk(1);
        $this->assertFalse($customerA->equals($customerB));
    }

    public function testARClassAsString(): void
    {
        $query = new ActiveQuery(Customer::class);

        $this->assertSame(Customer::class, $query->getARClass());
        $this->assertInstanceOf(Customer::class, $query->getARInstance());
    }

    public function testARClassAsInstance(): void
    {
        $customer = new Customer();
        $query = new ActiveQuery($customer);

        $this->assertSame($customer, $query->getARClass());
        $this->assertInstanceOf(Customer::class, $query->getARInstance());
    }

    public function testARClassAsClosure(): void
    {
        $closure = fn (): Customer => new Customer();
        $query = new ActiveQuery($closure);

        $this->assertSame($closure, $query->getARClass());
        $this->assertInstanceOf(Customer::class, $query->getARInstance());
    }
}
