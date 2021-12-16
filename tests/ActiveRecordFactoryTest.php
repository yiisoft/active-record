<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Redis\ActiveQuery as RedisActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerWithConstructor;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Customer as RedisCustomer;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\CustomerQuery as RedisCustomerQuery;
use Yiisoft\Db\Mysql\Connection as MysqlConnection;
use Yiisoft\Db\Redis\Connection as RedisConnection;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;

/**
 * @group main
 */
final class ActiveRecordFactoryTest extends TestCase
{
    protected string $driverName = 'sqlite';

    public function testCreateAR(): void
    {
        $customerAR = $this->arFactory->createAR(Customer::class);

        $this->assertInstanceOf(Customer::class, $customerAR);
    }

    public function testCreateARWithConnection(): void
    {
        $customerAR = $this->arFactory->createAR(Customer::class, $this->redisConnection);
        $db = $this->getInaccessibleProperty($customerAR, 'db', true);

        $this->assertInstanceOf(RedisConnection::class, $db);
        $this->assertInstanceOf(Customer::class, $customerAR);
    }

    public function testCreateQueryTo(): void
    {
        /** example create active query */
        $customerQuery = $this->arFactory->createQueryTo(Customer::class);

        $this->assertInstanceOf(ActiveQuery::class, $customerQuery);

        /** example create active query custom */
        $customerQuery = $this->arFactory->createQueryTo(Customer::class, CustomerQuery::class);

        $this->assertInstanceOf(CustomerQuery::class, $customerQuery);
    }

    public function testCreateQueryToWithConnection(): void
    {
        /** example create active query */
        $customerQuery = $this->arFactory->createQueryTo(Customer::class, CustomerQuery::class, $this->mysqlConnection);
        $db = $this->getInaccessibleProperty($customerQuery, 'db', true);

        $this->assertInstanceOf(MysqlConnection::class, $db);
        $this->assertInstanceOf(ActiveQuery::class, $customerQuery);
    }

    public function testCreateRedisQueryTo(): void
    {
        /** example create redis active query */
        $customerQuery = $this->arFactory->createRedisQueryTo(RedisCustomer::class);

        $this->assertInstanceOf(RedisActiveQuery::class, $customerQuery);

        /** example create redis active query custom */
        $customerQuery = $this->arFactory->createRedisQueryTo(RedisCustomer::class, RedisCustomerQuery::class);

        $this->assertInstanceOf(RedisCustomerQuery::class, $customerQuery);
    }

    public function testGetArInstanceWithConstructor(): void
    {
        $this->checkFixture($this->sqliteConnection, 'customer', true);

        $query = $this->arFactory->createQueryTo(CustomerWithConstructor::class);
        $customer = $query->one();

        $this->assertNotNull($customer->profile);
    }
}
