<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\ActiveRecord\Tests\Unit;

use yii\base\Event;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\Db\Connection;
use Yiisoft\Db\QueryBuilder;
use Yiisoft\Db\Tests\DatabaseTestCase;
use Yiisoft\Db\Tests\GetTablesAliasTestTrait;
use Yiisoft\ActiveRecord\Tests\Data\ActiveRecord;
use Yiisoft\ActiveRecord\Tests\Data\Customer;
use Yiisoft\ActiveRecord\Tests\Data\Profile;
use Yiisoft\ActiveRecord\ActiveQueryEvent;

/**
 * Class ActiveQueryTest the base class for testing ActiveQuery.
 */
abstract class ActiveQueryTest extends DatabaseTestCase
{
    public function setUp()
    {
        parent::setUp();
        ActiveRecord::$db = $this->getConnection();
    }

    public function testConstructor()
    {
        $config = [
            'on' => ['a' => 'b'],
            'joinWith' => ['dummy relation'],
        ];
        $query = new ActiveQuery(Customer::class, $config);
        $this->assertEquals($query->modelClass, Customer::class);
        $this->assertEquals($query->on, $config['on']);
        $this->assertEquals($query->joinWith, $config['joinWith']);
    }

    public function testTriggerInitEvent()
    {
        $where = '1==1';
        $callback = function (\yii\base\Event $event) use ($where) {
            $event->target->where = $where;
        };
        Event::on(\Yiisoft\ActiveRecord\ActiveQuery::class, ActiveQueryEvent::INIT, $callback);
        $result = $this->app->createObject(['__class' => ActiveQuery::class], [Customer::class]);
        $this->assertEquals($where, $result->where);
        Event::off(\Yiisoft\ActiveRecord\ActiveQuery::class, ActiveQueryEvent::INIT, $callback);
    }

    /**
     * @todo: tests for internal logic of prepare()
     */
    public function testPrepare()
    {
        $query = new ActiveQuery(Customer::class);
        $builder = new QueryBuilder(new Connection());
        $result = $query->prepare($builder);
        $this->assertInstanceOf('Yiisoft\Db\Query', $result);
    }

    public function testPopulate_EmptyRows()
    {
        $query = new ActiveQuery(Customer::class);
        $rows = [];
        $result = $query->populate([]);
        $this->assertEquals($rows, $result);
    }

    /**
     * @todo: tests for internal logic of populate()
     */
    public function testPopulate_FilledRows()
    {
        $query = new ActiveQuery(Customer::class);
        $rows = $query->all();
        $result = $query->populate($rows);
        $this->assertEquals($rows, $result);
    }

    /**
     * @todo: tests for internal logic of one()
     */
    public function testOne()
    {
        $query = new ActiveQuery(Customer::class);
        $result = $query->one();
        $this->assertInstanceOf('Yiisoft\ActiveRecord\Tests\Data\Customer', $result);
    }

    /**
     * @todo: test internal logic of createCommand()
     */
    public function testCreateCommand()
    {
        $query = new ActiveQuery(Customer::class);
        $result = $query->createCommand();
        $this->assertInstanceOf('Yiisoft\Db\Command', $result);
    }

    /**
     * @todo: tests for internal logic of queryScalar()
     */
    public function testQueryScalar()
    {
        $query = new ActiveQuery(Customer::class);
        $result = $this->invokeMethod($query, 'queryScalar', ['name', null]);
        $this->assertEquals('user1', $result);
    }

    /**
     * @todo: tests for internal logic of joinWith()
     */
    public function testJoinWith()
    {
        $query = new ActiveQuery(Customer::class);
        $result = $query->joinWith('profile');
        $this->assertEquals([
            [['profile'], true, 'LEFT JOIN'],
        ], $result->joinWith);
    }

    /**
     * @todo: tests for internal logic of innerJoinWith()
     */
    public function testInnerJoinWith()
    {
        $query = new ActiveQuery(Customer::class);
        $result = $query->innerJoinWith('profile');
        $this->assertEquals([
            [['profile'], true, 'INNER JOIN'],
        ], $result->joinWith);
    }

    /**
     * @todo: tests for the regex inside getQueryTableName
     */
    public function testGetQueryTableName_from_not_set()
    {
        $query = new ActiveQuery(Customer::class);
        $result = $this->invokeMethod($query, 'getTableNameAndAlias');
        $this->assertEquals(['customer', 'customer'], $result);
    }

    public function testGetQueryTableName_from_set()
    {
        $options = ['from' => ['alias' => 'customer']];
        $query = new ActiveQuery(Customer::class, $options);
        $result = $this->invokeMethod($query, 'getTableNameAndAlias');
        $this->assertEquals(['customer', 'alias'], $result);
    }

    public function testOnCondition()
    {
        $query = new ActiveQuery(Customer::class);
        $on = ['active' => true];
        $params = ['a' => 'b'];
        $result = $query->onCondition($on, $params);
        $this->assertEquals($on, $result->on);
        $this->assertEquals($params, $result->params);
    }

    public function testAndOnCondition_on_not_set()
    {
        $query = new ActiveQuery(Customer::class);
        $on = ['active' => true];
        $params = ['a' => 'b'];
        $result = $query->andOnCondition($on, $params);
        $this->assertEquals($on, $result->on);
        $this->assertEquals($params, $result->params);
    }

    public function testAndOnCondition_on_set()
    {
        $onOld = ['active' => true];
        $query = new ActiveQuery(Customer::class);
        $query->on = $onOld;

        $on = ['active' => true];
        $params = ['a' => 'b'];
        $result = $query->andOnCondition($on, $params);
        $this->assertEquals(['and', $onOld, $on], $result->on);
        $this->assertEquals($params, $result->params);
    }

    public function testOrOnCondition_on_not_set()
    {
        $query = new ActiveQuery(Customer::class);
        $on = ['active' => true];
        $params = ['a' => 'b'];
        $result = $query->orOnCondition($on, $params);
        $this->assertEquals($on, $result->on);
        $this->assertEquals($params, $result->params);
    }

    public function testOrOnCondition_on_set()
    {
        $onOld = ['active' => true];
        $query = new ActiveQuery(Customer::class);
        $query->on = $onOld;

        $on = ['active' => true];
        $params = ['a' => 'b'];
        $result = $query->orOnCondition($on, $params);
        $this->assertEquals(['or', $onOld, $on], $result->on);
        $this->assertEquals($params, $result->params);
    }

    /**
     * @todo: tests for internal logic of viaTable()
     */
    public function testViaTable()
    {
        $query = new ActiveQuery(Customer::class);
        $result = $query->viaTable(Profile::class, ['id' => 'item_id']);
        $this->assertInstanceOf('Yiisoft\ActiveRecord\ActiveQuery', $result);
        $this->assertInstanceOf('Yiisoft\ActiveRecord\ActiveQuery', $result->via);
    }

    public function testAlias_not_set()
    {
        $query = new ActiveQuery(Customer::class);
        $result = $query->alias('alias');
        $this->assertInstanceOf('Yiisoft\ActiveRecord\ActiveQuery', $result);
        $this->assertEquals(['alias' => 'customer'], $result->from);
    }

    public function testAlias_yet_set()
    {
        $aliasOld = ['old'];
        $query = new ActiveQuery(Customer::class);
        $query->from = $aliasOld;
        $result = $query->alias('alias');
        $this->assertInstanceOf('Yiisoft\ActiveRecord\ActiveQuery', $result);
        $this->assertEquals(['alias' => 'old'], $result->from);
    }

    use GetTablesAliasTestTrait;
    protected function createQuery()
    {
        return new ActiveQuery(null);
    }

    public function testGetTableNames_notFilledFrom()
    {
        $query = new ActiveQuery(Profile::class);

        $tables = $query->getTablesUsedInFrom();

        $this->assertEquals([
            '{{' . Profile::tableName() . '}}' => '{{' . Profile::tableName() . '}}',
        ], $tables);
    }

    public function testGetTableNames_wontFillFrom()
    {
        $query = new ActiveQuery(Profile::class);
        $this->assertEquals($query->from, null);
        $query->getTablesUsedInFrom();
        $this->assertEquals($query->from, null);
    }
}
