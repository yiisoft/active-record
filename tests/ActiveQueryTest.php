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
        $this->loadFixture(Customer::getConnection());

        $query = (new ActiveQuery(Customer::class))->on(['a' => 'b'])->joinWith('profile');

        $this->assertEquals($query->getModelClass(), Customer::class);
        $this->assertEquals($query->getOn(), ['a' => 'b']);
        $this->assertEquals($query->getJoinWith(), [[['profile'], true, 'LEFT JOIN']]);
    }

    public function testPrepare(): void
    {
        $builder = new QueryBuilder(Customer::getConnection());
        $query = (new ActiveQuery(Customer::class))->prepare($builder);

        $this->assertInstanceOf(Query::class, $query);
    }

    public function testPopulateEmptyRows(): void
    {
        $query = (new ActiveQuery(Customer::class))->populate([]);

        $this->assertEquals([], $query);
    }

    public function testPopulateFilledRows(): void
    {
        $query = new ActiveQuery(Customer::class);
        $rows = $query->all();
        $result = $query->populate($rows);

        $this->assertEquals($rows, $result);
    }

    public function testOne(): void
    {
        $query = (new ActiveQuery(Customer::class))->one();

        $this->assertInstanceOf(Customer::class, $query);
    }

    public function testCreateCommand(): void
    {
        $query = (new ActiveQuery(Customer::class))->createCommand();

        $this->assertInstanceOf(Command::class, $query);
    }

    public function testQueryScalar(): void
    {
        $query = new ActiveQuery(Customer::class);

        $result = $this->invokeMethod($query, 'queryScalar', ['name']);

        $this->assertEquals('user1', $result);
    }

    public function testJoinWith(): void
    {
        $query = (new ActiveQuery(Customer::class))->joinWith('profile');

        $this->assertEquals([[['profile'], true, 'LEFT JOIN']], $query->getJoinWith());
    }

    public function testInnerJoinWith(): void
    {
        $query = (new ActiveQuery(Customer::class))->innerJoinWith('profile');

        $this->assertEquals([[['profile'], true, 'INNER JOIN']], $query->getJoinWith());
    }

    public function testGetQueryTableNameFromNotSet(): void
    {
        $query = new ActiveQuery(Customer::class);

        $result = $this->invokeMethod($query, 'getTableNameAndAlias');

        $this->assertEquals(['customer', 'customer'], $result);
    }

    public function testGetQueryTableNameFromSet(): void
    {
        $query = (new ActiveQuery(Customer::class))->from(['alias' => 'customer']);

        $result = $this->invokeMethod($query, 'getTableNameAndAlias');

        $this->assertEquals(['customer', 'alias'], $result);
    }

    public function testOnCondition(): void
    {
        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = (new ActiveQuery(Customer::class))->onCondition($on, $params);

        $this->assertEquals($on, $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testAndOnConditionOnNotSet(): void
    {
        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = (new ActiveQuery(Customer::class))->andOnCondition($on, $params);

        $this->assertEquals($on, $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testAndOnConditionOnSet(): void
    {
        $onOld = ['active' => true];
        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = (new ActiveQuery(Customer::class))->on($onOld)->andOnCondition($on, $params);

        $this->assertEquals(['and', $onOld, $on], $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testOrOnConditionOnNotSet(): void
    {
        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = (new ActiveQuery(Customer::class))->orOnCondition($on, $params);

        $this->assertEquals($on, $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testOrOnConditionOnSet(): void
    {
        $onOld = ['active' => true];
        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = (new ActiveQuery(Customer::class))->on($onOld)->orOnCondition($on, $params);

        $this->assertEquals(['or', $onOld, $on], $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testViaTable(): void
    {
        $query = (new ActiveQuery(Customer::class))
            ->primaryModel(new Order())
            ->viaTable(Profile::class, ['id' => 'item_id']);

        $this->assertInstanceOf(ActiveQuery::class, $query);
        $this->assertInstanceOf(ActiveQuery::class, $query->getVia());
    }

    public function testAliasNotSet(): void
    {
        $query = (new ActiveQuery(Customer::class))->alias('alias');

        $this->assertInstanceOf(ActiveQuery::class, $query);
        $this->assertEquals(['alias' => 'customer'], $query->getFrom());
    }

    public function testAliasYetSet(): void
    {
        $aliasOld = ['old'];

        $query = (new ActiveQuery(Customer::class))->from($aliasOld)->alias('alias');

        $this->assertInstanceOf(ActiveQuery::class, $query);
        $this->assertEquals(['alias' => 'old'], $query->getFrom());
    }

    public function testGetTableNamesNotFilledFrom(): void
    {
        $query = (new ActiveQuery(Profile::class))->getTablesUsedInFrom();

        $this->assertEquals([
            '{{' . Profile::tableName() . '}}' => '{{' . Profile::tableName() . '}}',
        ], $query);
    }

    public function testGetTableNamesWontFillFrom(): void
    {
        $query = new ActiveQuery(Profile::class);

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
        $categories = new Category();

        $categories = $categories->find()->with('orders')->indexBy('id')->all();

        $category = $categories[1];

        $this->assertNotNull($category);

        $orders = $category->orders;

        $this->assertCount(2, $orders);
        $this->assertInstanceOf(Order::class, $orders[0]);
        $this->assertInstanceOf(Order::class, $orders[1]);

        $ids = [$orders[0]->id, $orders[1]->id];

        \sort($ids);

        $this->assertEquals([1, 3], $ids);

        $category = $categories[2];

        $this->assertNotNull($category);

        $orders = $category->orders;

        $this->assertCount(1, $orders);
        $this->assertInstanceOf(Order::class, $orders[0]);
        $this->assertEquals(2, $orders[0]->id);
    }
}
