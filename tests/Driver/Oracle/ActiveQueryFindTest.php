<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Oracle;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Order;
use Yiisoft\ActiveRecord\Tests\Support\OracleHelper;

final class ActiveQueryFindTest extends \Yiisoft\ActiveRecord\Tests\ActiveQueryFindTest
{
    public function setUp(): void
    {
        parent::setUp();

        $oracleHelper = new OracleHelper();
        $this->db = $oracleHelper->createConnection();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->db->close();

        unset($this->db);
    }

    public function testFindEager(): void
    {
        $this->checkFixture($this->db, 'customer', true);

        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customers = $customerQuery->with('orders')->indexBy('id')->all();

        ksort($customers);
        $this->assertCount(3, $customers);
        $this->assertTrue($customers[1]->isRelationPopulated('orders'));
        $this->assertTrue($customers[2]->isRelationPopulated('orders'));
        $this->assertTrue($customers[3]->isRelationPopulated('orders'));
        $this->assertCount(1, $customers[1]->getOrders());
        $this->assertCount(2, $customers[2]->getOrders());
        $this->assertCount(0, $customers[3]->getOrders());

        $customers[1]->resetRelation('orders');
        $this->assertFalse($customers[1]->isRelationPopulated('orders'));

        $customer = $customerQuery->where(['id' => 1])->with('orders')->onePopulate();
        $this->assertTrue($customer->isRelationPopulated('orders'));
        $this->assertCount(1, $customer->getOrders());
        $this->assertCount(1, $customer->getRelatedRecords());

        /** multiple with() calls */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $orders = $orderQuery->with('customer', 'items')->all();
        $this->assertCount(3, $orders);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[0]->isRelationPopulated('items'));

        $orders = $orderQuery->with('customer')->with('items')->all();
        $this->assertCount(3, $orders);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
    }

    public function testFindAsArray(): void
    {
        $this->checkFixture($this->db, 'customer');

        /** asArray */
        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customer = $customerQuery->where(['[[id]]' => 2])->asArray()->onePopulate();
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
