<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerWithConstructor;
use Yiisoft\Db\Mysql\Connection as MysqlConnection;

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
        $customerAR = $this->arFactory->createAR(Customer::class, $this->mysqlConnection);
        $db = $this->getInaccessibleProperty($customerAR, 'db', true);

        $this->assertInstanceOf(MysqlConnection::class, $db);
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

    public function testGetArInstanceWithConstructor(): void
    {
        $this->checkFixture($this->sqliteConnection, 'customer', true);

        $query = $this->arFactory->createQueryTo(CustomerWithConstructor::class);
        $customer = $query->one();

        $this->assertNotNull($customer->profile);
    }
}
