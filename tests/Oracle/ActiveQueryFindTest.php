<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Oracle;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\ActiveQueryFindTest as AbstractActiveQueryFindTest;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Order;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * @group oci
 */
final class ActiveQueryFindTest extends AbstractActiveQueryFindTest
{
    protected string $driverName = 'oci';
    protected ConnectionInterface $db;

    public function setUp(): void
    {
        parent::setUp();

        $this->db = $this->ociConnection;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->ociConnection->close();

        unset($this->ociConnection);
    }

    public function testFindEager(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customers = $customerQuery
            ->with('orders')
            ->indexBy('id')
            ->all();

        ksort($customers);
        $this->assertCount(3, $customers);
        $this->assertTrue($customers[1]->isRelationPopulated('orders'));
        $this->assertTrue($customers[2]->isRelationPopulated('orders'));
        $this->assertTrue($customers[3]->isRelationPopulated('orders'));
        $this->assertCount(1, $customers[1]->orders);
        $this->assertCount(2, $customers[2]->orders);
        $this->assertCount(0, $customers[3]->orders);

        unset($customers[1]->orders);
        $this->assertFalse($customers[1]->isRelationPopulated('orders'));

        $customer = $customerQuery
            ->where(['id' => 1])
            ->with('orders')
            ->one();
        $this->assertTrue($customer->isRelationPopulated('orders'));
        $this->assertCount(1, $customer->orders);
        $this->assertCount(1, $customer->relatedRecords);

        /** multiple with() calls */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $orders = $orderQuery
            ->with('customer', 'items')
            ->all();
        $this->assertCount(3, $orders);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[0]->isRelationPopulated('items'));

        $orders = $orderQuery
            ->with('customer')
            ->with('items')
            ->all();
        $this->assertCount(3, $orders);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
    }

    public function testFindAsArray(): void
    {
        $this->checkFixture($this->db, 'customer');

        /** asArray */
        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customer = $customerQuery
            ->where(['[[id]]' => 2])
            ->asArray()
            ->one();
        $this->assertEquals([
            'id' => 2,
            'email' => 'user2@example.com',
            'name' => 'user2',
            'address' => 'address2',
            'status' => 1,
            'profile_id' => null,
            'bool_status' => true,
        ], $customer);

        /** find all asArray */
        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customers = $customerQuery
            ->asArray()
            ->all();
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
