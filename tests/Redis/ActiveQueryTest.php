<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Redis;

use Yiisoft\ActiveRecord\BaseActiveRecord;
use Yiisoft\ActiveRecord\Redis\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\TestCase;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Category;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Order;

/**
 * @group redis
 */
final class ActiveQueryTest extends TestCase
{
    protected ?string $driverName = 'redis';

    public function setUp(): void
    {
        parent::setUp();

        BaseActiveRecord::connectionId($this->driverName);

        $this->redisConnection->open();
        $this->redisConnection->flushdb();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->redisConnection->close();

        unset($this->redisConnection);
    }

    public function testOptions(): void
    {
        $query = (new ActiveQuery(Customer::class))->on(['a' => 'b'])->joinWith('profile');

        $this->assertEquals($query->getModelClass(), Customer::class);
        $this->assertEquals($query->getOn(), ['a' => 'b']);
        $this->assertEquals($query->getJoinWith(), [[['profile'], true, 'LEFT JOIN']]);
    }

    public function testPopulateEmptyRows(): void
    {
        $query = (new ActiveQuery(Customer::class))->populate([]);

        $this->assertEquals([], $query);
    }

    public function testPopulateFilledRows(): void
    {
        $this->customerData();

        $query = new ActiveQuery(Customer::class);

        $rows = $query->all();

        $result = $query->populate($rows);

        $this->assertEquals($rows, $result);
    }

    public function testOne(): void
    {
        $this->customerData();

        $query = (new ActiveQuery(Customer::class))->one();

        $this->assertInstanceOf(Customer::class, $query);
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

    public function testAliasYetSet(): void
    {
        $aliasOld = ['old'];

        $query = (new ActiveQuery(Customer::class))->from($aliasOld)->alias('alias');

        $this->assertInstanceOf(ActiveQuery::class, $query);
        $this->assertEquals(['alias' => 'old'], $query->getFrom());
    }
}
