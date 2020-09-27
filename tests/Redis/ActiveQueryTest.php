<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Redis;

use Yiisoft\ActiveRecord\Redis\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\TestCase;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Customer;

/**
 * @group redis
 */
final class ActiveQueryTest extends TestCase
{
    protected string $driverName = 'redis';

    public function setUp(): void
    {
        parent::setUp();

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
        $query = new ActiveQuery(Customer::class, $this->redisConnection);

        $query = $query->on(['a' => 'b'])->joinWith('profile');

        $this->assertEquals(Customer::class, $query->getARClass());
        $this->assertEquals(['a' => 'b'], $query->getOn());
        $this->assertEquals([[['profile'], true, 'LEFT JOIN']], $query->getJoinWith());
    }

    public function testPopulateEmptyRows(): void
    {
        $query = new ActiveQuery(Customer::class, $this->redisConnection);

        $query = $query->populate([]);

        $this->assertEquals([], $query);
    }

    public function testPopulateFilledRows(): void
    {
        $this->customerData();

        $query = new ActiveQuery(Customer::class, $this->redisConnection);

        $rows = $query->all();

        $result = $query->populate($rows);

        $this->assertEquals($rows, $result);
    }

    public function testOne(): void
    {
        $this->customerData();

        $query = new ActiveQuery(Customer::class, $this->redisConnection);

        $query = $query->one();

        $this->assertInstanceOf(Customer::class, $query);
    }

    public function testJoinWith(): void
    {
        $query = new ActiveQuery(Customer::class, $this->redisConnection);

        $query = $query->joinWith('profile');

        $this->assertEquals([[['profile'], true, 'LEFT JOIN']], $query->getJoinWith());
    }

    public function testInnerJoinWith(): void
    {
        $query = new ActiveQuery(Customer::class, $this->redisConnection);

        $query = $query->innerJoinWith('profile');

        $this->assertEquals([[['profile'], true, 'INNER JOIN']], $query->getJoinWith());
    }

    public function testOnCondition(): void
    {
        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = new ActiveQuery(Customer::class, $this->redisConnection);

        $query = $query->onCondition($on, $params);

        $this->assertEquals($on, $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testAndOnConditionOnNotSet(): void
    {
        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = new ActiveQuery(Customer::class, $this->redisConnection);

        $query = $query->andOnCondition($on, $params);

        $this->assertEquals($on, $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testAndOnConditionOnSet(): void
    {
        $onOld = ['active' => true];
        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = new ActiveQuery(Customer::class, $this->redisConnection);

        $query = $query->on($onOld)->andOnCondition($on, $params);

        $this->assertEquals(['and', $onOld, $on], $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testOrOnConditionOnNotSet(): void
    {
        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = new ActiveQuery(Customer::class, $this->redisConnection);

        $query = $query->orOnCondition($on, $params);

        $this->assertEquals($on, $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testOrOnConditionOnSet(): void
    {
        $onOld = ['active' => true];
        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = new ActiveQuery(Customer::class, $this->redisConnection);

        $query = $query->on($onOld)->orOnCondition($on, $params);

        $this->assertEquals(['or', $onOld, $on], $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testAliasYetSet(): void
    {
        $aliasOld = ['old'];

        $query = new ActiveQuery(Customer::class, $this->redisConnection);

        $query = $query->from($aliasOld)->alias('alias');

        $this->assertInstanceOf(ActiveQuery::class, $query);
        $this->assertEquals(['alias' => 'old'], $query->getFrom());
    }
}
