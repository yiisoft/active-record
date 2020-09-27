<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Redis;

use Yiisoft\ActiveRecord\Redis\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\TestCase;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Customer;

/**
 * @group redis
 */
final class ActiveQueryFactoryTest extends TestCase
{
    protected string $driverName = 'redis';

    public function setUp(): void
    {
        parent::setUp();

        $this->redisConnection->open();
        $this->redisConnection->flushdb();

        $this->arFactory->withConnection($this->redisConnection);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->redisConnection->close();

        unset($this->arFactory, $this->redisConnection);
    }

    public function testOptions(): void
    {
        $query = $this->arFactory->createRedisQueryTo(Customer::class);

        $query = $query->on(['a' => 'b'])->joinWith('profile');

        $this->assertEquals($query->getARClass(), Customer::class);
        $this->assertEquals($query->getOn(), ['a' => 'b']);
        $this->assertEquals($query->getJoinWith(), [[['profile'], true, 'LEFT JOIN']]);
    }

    public function testPopulateEmptyRows(): void
    {
        $query = $this->arFactory->createRedisQueryTo(Customer::class);

        $query = $query->populate([]);

        $this->assertEquals([], $query);
    }

    public function testPopulateFilledRows(): void
    {
        $this->customerData();

        $query = $this->arFactory->createRedisQueryTo(Customer::class);

        $rows = $query->all();

        $result = $query->populate($rows);

        $this->assertEquals($rows, $result);
    }

    public function testOne(): void
    {
        $this->customerData();

        $query = $this->arFactory->createRedisQueryTo(Customer::class);

        $query = $query->one();

        $this->assertInstanceOf(Customer::class, $query);
    }

    public function testJoinWith(): void
    {
        $query = $this->arFactory->createRedisQueryTo(Customer::class);

        $query = $query->joinWith('profile');

        $this->assertEquals([[['profile'], true, 'LEFT JOIN']], $query->getJoinWith());
    }

    public function testInnerJoinWith(): void
    {
        $query = $this->arFactory->createRedisQueryTo(Customer::class);

        $query = $query->innerJoinWith('profile');

        $this->assertEquals([[['profile'], true, 'INNER JOIN']], $query->getJoinWith());
    }

    public function testOnCondition(): void
    {
        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = $this->arFactory->createRedisQueryTo(Customer::class);

        $query = $query->onCondition($on, $params);

        $this->assertEquals($on, $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testAndOnConditionOnNotSet(): void
    {
        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = $this->arFactory->createRedisQueryTo(Customer::class);

        $query = $query->andOnCondition($on, $params);

        $this->assertEquals($on, $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testAndOnConditionOnSet(): void
    {
        $onOld = ['active' => true];
        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = $this->arFactory->createRedisQueryTo(Customer::class);

        $query = $query->on($onOld)->andOnCondition($on, $params);

        $this->assertEquals(['and', $onOld, $on], $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testOrOnConditionOnNotSet(): void
    {
        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = $this->arFactory->createRedisQueryTo(Customer::class);

        $query = $query->orOnCondition($on, $params);

        $this->assertEquals($on, $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testOrOnConditionOnSet(): void
    {
        $onOld = ['active' => true];
        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = $this->arFactory->createRedisQueryTo(Customer::class);

        $query = $query->on($onOld)->orOnCondition($on, $params);

        $this->assertEquals(['or', $onOld, $on], $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testAliasYetSet(): void
    {
        $aliasOld = ['old'];

        $query = $this->arFactory->createRedisQueryTo(Customer::class);

        $query = $query->from($aliasOld)->alias('alias');

        $this->assertInstanceOf(ActiveQuery::class, $query);
        $this->assertEquals(['alias' => 'old'], $query->getFrom());
    }
}
