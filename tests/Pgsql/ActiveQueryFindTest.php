<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Pgsql;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\ActiveQueryFindTest as AbstractActiveQueryFindTest;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * @group pgsql
 */
final class ActiveQueryFindTest extends AbstractActiveQueryFindTest
{
    protected string $driverName = 'pgsql';
    protected ConnectionInterface $db;

    public function setUp(): void
    {
        parent::setUp();

        $this->db = $this->pgsqlConnection;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->pgsqlConnection->close();

        unset($this->pgsqlConnection);
    }

    public function testFindAsArray(): void
    {
        $this->loadFixture($this->db);

        /** asArray */
        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customer = $customerQuery->where(['id' => 2])->asArray()->one();
        $this->assertEquals([
            'id' => 2,
            'email' => 'user2@example.com',
            'name' => 'user2',
            'address' => 'address2',
            'status' => 1,
            'bool_status' => true,
            'profile_id' => null,
        ], $customer);

        /** find all asArray */
        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customers = $customerQuery->asArray()->all();
        $this->assertCount(3, $customers);
        $this->assertArrayHasKey('id', $customers[0]);
        $this->assertArrayHasKey('name', $customers[0]);
        $this->assertArrayHasKey('email', $customers[0]);
        $this->assertArrayHasKey('address', $customers[0]);
        $this->assertArrayHasKey('status', $customers[0]);
        $this->assertArrayHasKey('bool_status', $customers[0]);
        $this->assertArrayHasKey('id', $customers[1]);
        $this->assertArrayHasKey('name', $customers[1]);
        $this->assertArrayHasKey('email', $customers[1]);
        $this->assertArrayHasKey('address', $customers[1]);
        $this->assertArrayHasKey('status', $customers[1]);
        $this->assertArrayHasKey('bool_status', $customers[1]);
        $this->assertArrayHasKey('id', $customers[2]);
        $this->assertArrayHasKey('name', $customers[2]);
        $this->assertArrayHasKey('email', $customers[2]);
        $this->assertArrayHasKey('address', $customers[2]);
        $this->assertArrayHasKey('status', $customers[2]);
        $this->assertArrayHasKey('bool_status', $customers[2]);
    }
}
