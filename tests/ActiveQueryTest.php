<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Category;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Order;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Profile;
use Yiisoft\Db\Command\Command;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Query\QueryBuilder;

abstract class ActiveQueryTest extends TestCase
{
    public function testOptions(): void
    {
        $this->loadFixture($this->db);

        $customer = new Customer($this->db);

        $query = $customer->find()->on(['a' => 'b'])->joinWith('profile');

        $this->assertEquals($query->getARClass(), Customer::class);
        $this->assertEquals($query->getOn(), ['a' => 'b']);
        $this->assertEquals($query->getJoinWith(), [[['profile'], true, 'LEFT JOIN']]);
    }

    public function testPrepare(): void
    {
        $builder = new QueryBuilder($this->db);

        $query = new ActiveQuery(Customer::class, $this->db);

        $this->assertInstanceOf(Query::class, $query->prepare($builder));
    }

    public function testPopulateEmptyRows(): void
    {
        $query = new ActiveQuery(Customer::class, $this->db);

        $this->assertEquals([], $query->populate([]));
    }

    public function testPopulateFilledRows(): void
    {
        $query = new ActiveQuery(Customer::class, $this->db);

        $rows = $query->all();

        $result = $query->populate($rows);

        $this->assertEquals($rows, $result);
    }

    public function testOne(): void
    {
        $query = new ActiveQuery(Customer::class, $this->db);

        $this->assertInstanceOf(Customer::class, $query->one());
    }

    public function testCreateCommand(): void
    {
        $query = new ActiveQuery(Customer::class, $this->db);

        $this->assertInstanceOf(Command::class, $query->createCommand());
    }

    public function testQueryScalar(): void
    {
        $query = new ActiveQuery(Customer::class, $this->db);

        $this->assertEquals('user1', $this->invokeMethod($query, 'queryScalar', ['name']));
    }

    public function testJoinWith(): void
    {
        $query = new ActiveQuery(Customer::class, $this->db);

        $query->joinWith('profile');

        $this->assertEquals([[['profile'], true, 'LEFT JOIN']], $query->getJoinWith());
    }

    public function testInnerJoinWith(): void
    {
        $query = new ActiveQuery(Customer::class, $this->db);

        $query->innerJoinWith('profile');

        $this->assertEquals([[['profile'], true, 'INNER JOIN']], $query->getJoinWith());
    }

    public function testBuildJoinWithRemoveDuplicateJoinByTableName(): void
    {
        $query = new ActiveQuery(Customer::class, $this->db);

        $query->innerJoinWith('orders')->joinWith('orders.orderItems');

        $this->invokeMethod($query, 'buildJoinWith');

        $this->assertEquals([
            [
                'INNER JOIN',
                'order',
                '{{customer}}.[[id]] = {{order}}.[[customer_id]]'
            ],
            [
                'LEFT JOIN',
                'order_item',
                '{{order}}.[[id]] = {{order_item}}.[[order_id]]'
            ],
        ], $query->getJoin());
    }

    public function testGetQueryTableNameFromNotSet(): void
    {
        $query = new ActiveQuery(Customer::class, $this->db);

        $this->assertEquals(['customer', 'customer'], $this->invokeMethod($query, 'getTableNameAndAlias'));
    }

    public function testGetQueryTableNameFromSet(): void
    {
        $query = new ActiveQuery(Customer::class, $this->db);

        $query->from(['alias' => 'customer']);

        $this->assertEquals(['customer', 'alias'], $this->invokeMethod($query, 'getTableNameAndAlias'));
    }

    public function testOnCondition(): void
    {
        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = new ActiveQuery(Customer::class, $this->db);

        $query->onCondition($on, $params);

        $this->assertEquals($on, $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testAndOnConditionOnNotSet(): void
    {
        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = new ActiveQuery(Customer::class, $this->db);

        $query->andOnCondition($on, $params);

        $this->assertEquals($on, $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testAndOnConditionOnSet(): void
    {
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
        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = new ActiveQuery(Customer::class, $this->db);

        $query->orOnCondition($on, $params);

        $this->assertEquals($on, $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testOrOnConditionOnSet(): void
    {
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
        $order = new Order($this->db);

        $query = new ActiveQuery(Customer::class, $this->db);

        $query->primaryModel($order)->viaTable(Profile::class, ['id' => 'item_id']);

        $this->assertInstanceOf(ActiveQuery::class, $query);
        $this->assertInstanceOf(ActiveQuery::class, $query->getVia());
    }

    public function testAliasNotSet(): void
    {
        $query = new ActiveQuery(Customer::class, $this->db);

        $query->alias('alias');

        $this->assertInstanceOf(ActiveQuery::class, $query);
        $this->assertEquals(['alias' => 'customer'], $query->getFrom());
    }

    public function testAliasYetSet(): void
    {
        $aliasOld = ['old'];

        $query = new ActiveQuery(Customer::class, $this->db);

        $query->from($aliasOld)->alias('alias');

        $this->assertInstanceOf(ActiveQuery::class, $query);
        $this->assertEquals(['alias' => 'old'], $query->getFrom());
    }

    public function testGetTableNamesNotFilledFrom(): void
    {
        $query = new ActiveQuery(Profile::class, $this->db);

        $tableName = $query->getARInstance()->tableName();

        $this->assertEquals(
            [
                '{{' . $tableName . '}}' => '{{' . $tableName . '}}',
            ],
            $query->getTablesUsedInFrom()
        );
    }

    public function testGetTableNamesWontFillFrom(): void
    {
        $query = new ActiveQuery(Profile::class, $this->db);

        $this->assertEquals($query->getFrom(), null);

        $query->getTablesUsedInFrom();

        $this->assertEquals($query->getFrom(), null);
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/5341}
     *
     * Issue: Plan 1 -- * Account * -- * User
     * Our Tests: Category 1 -- * Item * -- * Order
     */
    public function testDeeplyNestedTableRelationWith(): void
    {
        /** @var $category Category */
        $categories = new Category($this->db);

        $categories = $categories->find()->with('orders')->indexBy('id')->all();

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
        $query = new ActiveQuery(Customer::class, $this->db);

        $query->sql('SELECT * FROM {{customer}} ORDER BY [[id]] DESC');

        $this->assertEquals('SELECT * FROM {{customer}} ORDER BY [[id]] DESC', $query->getSql());
    }
}
