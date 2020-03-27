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

        $query = new ActiveQuery(Customer::class);
        $query->setOn(['a' => 'b']);
        $query->setJoinWith(['dummy relation']);

        $this->assertEquals($query->getModelClass(), Customer::class);
        $this->assertEquals($query->getOn(), ['a' => 'b']);
        $this->assertEquals($query->getJoinWith(), ['dummy relation']);
    }

    public function testPrepare(): void
    {
        $query = new ActiveQuery(Customer::class);
        $builder = new QueryBuilder(Customer::getConnection());

        $result = $query->prepare($builder);

        $this->assertInstanceOf(Query::class, $result);
    }

    public function testPopulateEmptyRows(): void
    {
        $query = new ActiveQuery(Customer::class);

        $rows = [];

        $result = $query->populate([]);

        $this->assertEquals($rows, $result);
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
        $query = new ActiveQuery(Customer::class);

        $result = $query->one();

        $this->assertInstanceOf(Customer::class, $result);
    }

    public function testCreateCommand(): void
    {
        $query = new ActiveQuery(Customer::class);

        $result = $query->createCommand();

        $this->assertInstanceOf(Command::class, $result);
    }

    public function testQueryScalar(): void
    {
        $query = new ActiveQuery(Customer::class);

        $result = $this->invokeMethod($query, 'queryScalar', ['name']);

        $this->assertEquals('user1', $result);
    }

    public function testJoinWith(): void
    {
        $query = new ActiveQuery(Customer::class);

        $result = $query->joinWith('profile');

        $this->assertEquals([[['profile'], true, 'LEFT JOIN']], $result->getJoinWith());
    }

    public function testInnerJoinWith(): void
    {
        $query = new ActiveQuery(Customer::class);

        $result = $query->innerJoinWith('profile');

        $this->assertEquals([[['profile'], true, 'INNER JOIN']], $result->getJoinWith());
    }

    public function testGetQueryTableNameFromNotSet(): void
    {
        $query = new ActiveQuery(Customer::class);

        $result = $this->invokeMethod($query, 'getTableNameAndAlias');

        $this->assertEquals(['customer', 'customer'], $result);
    }

    public function testGetQueryTableNameFromSet(): void
    {
        $query = new ActiveQuery(Customer::class);
        $query->from(['alias' => 'customer']);

        $result = $this->invokeMethod($query, 'getTableNameAndAlias');

        $this->assertEquals(['customer', 'alias'], $result);
    }

    public function testOnCondition(): void
    {
        $query = new ActiveQuery(Customer::class);

        $on = ['active' => true];
        $params = ['a' => 'b'];

        $result = $query->onCondition($on, $params);

        $this->assertEquals($on, $result->getOn());
        $this->assertEquals($params, $result->getParams());
    }

    public function testAndOnConditionOnNotSet(): void
    {
        $query = new ActiveQuery(Customer::class);

        $on = ['active' => true];
        $params = ['a' => 'b'];
        $result = $query->andOnCondition($on, $params);

        $this->assertEquals($on, $result->getOn());
        $this->assertEquals($params, $result->getParams());
    }

    public function testAndOnConditionOnSet(): void
    {
        $onOld = ['active' => true];

        $query = new ActiveQuery(Customer::class);

        $query->setOn($onOld);

        $on = ['active' => true];
        $params = ['a' => 'b'];

        $result = $query->andOnCondition($on, $params);

        $this->assertEquals(['and', $onOld, $on], $result->getOn());
        $this->assertEquals($params, $result->getParams());
    }

    public function testOrOnConditionOnNotSet(): void
    {
        $query = new ActiveQuery(Customer::class);

        $on = ['active' => true];
        $params = ['a' => 'b'];
        $result = $query->orOnCondition($on, $params);

        $this->assertEquals($on, $result->getOn());
        $this->assertEquals($params, $result->getParams());
    }

    public function testOrOnConditionOnSet(): void
    {
        $onOld = ['active' => true];

        $query = new ActiveQuery(Customer::class);

        $query->setOn($onOld);

        $on = ['active' => true];
        $params = ['a' => 'b'];

        $result = $query->orOnCondition($on, $params);

        $this->assertEquals(['or', $onOld, $on], $result->getOn());
        $this->assertEquals($params, $result->getParams());
    }

    public function testViaTable(): void
    {
        $query = new ActiveQuery(
            Customer::class,
            ['primaryModel' => new Order()]
        );

        $result = $query->viaTable(Profile::class, ['id' => 'item_id']);

        $this->assertInstanceOf(ActiveQuery::class, $result);
        $this->assertInstanceOf(ActiveQuery::class, $result->getVia());
    }

    public function testAliasNotSet(): void
    {
        $query = new ActiveQuery(Customer::class);

        $result = $query->alias('alias');

        $this->assertInstanceOf(ActiveQuery::class, $result);
        $this->assertEquals(['alias' => 'customer'], $result->getFrom());
    }

    public function testAliasYetSet(): void
    {
        $aliasOld = ['old'];

        $query = new ActiveQuery(Customer::class);

        $query->from($aliasOld);

        $result = $query->alias('alias');

        $this->assertInstanceOf(ActiveQuery::class, $result);
        $this->assertEquals(['alias' => 'old'], $result->getFrom());
    }

    public function testGetTableNamesNotFilledFrom(): void
    {
        $query = new ActiveQuery(Profile::class);

        $tables = $query->getTablesUsedInFrom();

        $this->assertEquals([
            '{{' . Profile::tableName() . '}}' => '{{' . Profile::tableName() . '}}',
        ], $tables);
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
