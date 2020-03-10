<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\Category;
use Yiisoft\ActiveRecord\Tests\Stubs\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\Item;
use Yiisoft\ActiveRecord\Tests\Stubs\Order;
use Yiisoft\ActiveRecord\Tests\Stubs\OrderItem;
use Yiisoft\ActiveRecord\Tests\Stubs\Profile;
use Yiisoft\Db\Command\Command;
use Yiisoft\Db\Connection\Connection;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Query\QueryBuilder;
use Yiisoft\Db\Tests\DatabaseTestCase;

/**
 * Class ActiveQueryTest the base class for testing ActiveQuery.
 */
abstract class ActiveQueryTest extends DatabaseTestCase
{
    protected ?Connection $db = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = $this->getConnection();
    }

    public function testOptions(): void
    {
        Customer::setDriverName($this->driverName);

        $this->db = $this->getConnection(true, true, true);

        $query = new ActiveQuery(Customer::class);
        $query->setOn(['a' => 'b']);
        $query->setJoinWith(['dummy relation']);

        $this->assertEquals($query->modelClass, Customer::class);
        $this->assertEquals($query->on, ['a' => 'b']);
        $this->assertEquals($query->joinWith, ['dummy relation']);
    }

    /**
     * @todo: tests for internal logic of prepare()
     */
    public function testPrepare(): void
    {
        $query = new ActiveQuery(Customer::class);
        $builder = new QueryBuilder($this->db);

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

    /**
     * @todo: tests for internal logic of populate()
     */
    public function testPopulateFilledRows(): void
    {
        $query = new ActiveQuery(Customer::class);

        $rows = $query->all();

        $result = $query->populate($rows);

        $this->assertEquals($rows, $result);
    }

    /**
     * @todo: tests for internal logic of one()
     */
    public function testOne(): void
    {
        $query = new ActiveQuery(Customer::class);

        $result = $query->one();

        $this->assertInstanceOf(Customer::class, $result);
    }

    /**
     * @todo: test internal logic of createCommand()
     */
    public function testCreateCommand(): void
    {
        $query = new ActiveQuery(Customer::class);

        $result = $query->createCommand();

        $this->assertInstanceOf(Command::class, $result);
    }

    /**
     * @todo: tests for internal logic of queryScalar()
     */
    public function testQueryScalar(): void
    {
        $query = new ActiveQuery(Customer::class);

        $result = $this->invokeMethod($query, 'queryScalar', ['name']);

        $this->assertEquals('user1', $result);
    }

    /**
     * @todo: tests for internal logic of joinWith()
     */
    public function testJoinWith(): void
    {
        $query = new ActiveQuery(Customer::class);

        $result = $query->joinWith('profile');

        $this->assertEquals([[['profile'], true, 'LEFT JOIN']], $result->joinWith);
    }

    /**
     * @todo: tests for internal logic of innerJoinWith()
     */
    public function testInnerJoinWith(): void
    {
        $query = new ActiveQuery(Customer::class);

        $result = $query->innerJoinWith('profile');

        $this->assertEquals([[['profile'], true, 'INNER JOIN']], $result->joinWith);
    }

    /**
     * @todo: tests for the regex inside getQueryTableName
     */
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

        $this->assertEquals($on, $result->on);
        $this->assertEquals($params, $result->getParams());
    }

    public function testAndOnConditionOnNotSet(): void
    {
        $query = new ActiveQuery(Customer::class);

        $on = ['active' => true];
        $params = ['a' => 'b'];
        $result = $query->andOnCondition($on, $params);

        $this->assertEquals($on, $result->on);
        $this->assertEquals($params, $result->getParams());
    }

    public function testAndOnConditionOnSet(): void
    {
        $onOld = ['active' => true];

        $query = new ActiveQuery(Customer::class);

        $query->on = $onOld;

        $on = ['active' => true];
        $params = ['a' => 'b'];

        $result = $query->andOnCondition($on, $params);

        $this->assertEquals(['and', $onOld, $on], $result->on);
        $this->assertEquals($params, $result->getParams());
    }

    public function testOrOnConditionOnNotSet(): void
    {
        $query = new ActiveQuery(Customer::class);

        $on = ['active' => true];
        $params = ['a' => 'b'];
        $result = $query->orOnCondition($on, $params);

        $this->assertEquals($on, $result->on);
        $this->assertEquals($params, $result->getParams());
    }

    public function testOrOnConditionOnSet(): void
    {
        $onOld = ['active' => true];

        $query = new ActiveQuery(Customer::class);

        $query->on = $onOld;

        $on = ['active' => true];
        $params = ['a' => 'b'];

        $result = $query->orOnCondition($on, $params);

        $this->assertEquals(['or', $onOld, $on], $result->on);
        $this->assertEquals($params, $result->getParams());
    }

    /**
     * @todo: tests for internal logic of viaTable()
     */
    public function testViaTable(): void
    {
        Order::setDriverName($this->driverName);

        $query = new ActiveQuery(
            Customer::class,
            ['primaryModel' => new Order($this->db)]
        );

        $result = $query->viaTable(Profile::class, ['id' => 'item_id']);

        $this->assertInstanceOf(ActiveQuery::class, $result);
        $this->assertInstanceOf(ActiveQuery::class, $result->via);
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

    use GetTablesAliasTestTrait;

    protected function createQuery(Connection $db): Query
    {
        return new Query($this->db);
    }

    public function testGetTableNamesNotFilledFrom(): void
    {
        Profile::setDriverName($this->driverName);

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
     * https://github.com/yiisoft/yii2/issues/5341
     *
     * Issue:     Plan     1 -- * Account * -- * User
     * Our Tests: Category 1 -- * Item    * -- * Order
     */
    public function testDeeplyNestedTableRelationWith(): void
    {
        Category::setDriverName($this->driverName);
        OrderItem::setDriverName($this->driverName);
        Item::setDriverName($this->driverName);

        /* @var $category Category */
        $categories = new Category();
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
}
