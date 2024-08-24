<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Oracle;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Driver\Oracle\Stubs\Customer as CustomerWithRownumid;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Order;
use Yiisoft\ActiveRecord\Tests\Support\OracleHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

final class ActiveQueryFindTest extends \Yiisoft\ActiveRecord\Tests\ActiveQueryFindTest
{
    protected function createConnection(): ConnectionInterface
    {
        return (new OracleHelper())->createConnection();
    }

    public function testFindLimit(): void
    {
        $this->checkFixture($this->db(), 'customer', true);

        /** one */
        $customerQuery = new ActiveQuery(CustomerWithRownumid::class);
        $customer = $customerQuery->orderBy('id')->one();
        $this->assertEquals('user1', $customer->getName());

        /** all */
        $customerQuery = new ActiveQuery(CustomerWithRownumid::class);
        $customers = $customerQuery->all();
        $this->assertCount(3, $customers);

        /** limit */
        $customerQuery = new ActiveQuery(CustomerWithRownumid::class);
        $customers = $customerQuery->orderBy('id')->limit(1)->all();
        $this->assertCount(1, $customers);
        $this->assertEquals('user1', $customers[0]->getName());

        $customers = $customerQuery->orderBy('id')->limit(1)->offset(1)->all();
        $this->assertCount(1, $customers);
        $this->assertEquals('user2', $customers[0]->getName());

        $customers = $customerQuery->orderBy('id')->limit(1)->offset(2)->all();
        $this->assertCount(1, $customers);
        $this->assertEquals('user3', $customers[0]->getName());

        $customers = $customerQuery->orderBy('id')->limit(2)->offset(1)->all();
        $this->assertCount(2, $customers);
        $this->assertEquals('user2', $customers[0]->getName());
        $this->assertEquals('user3', $customers[1]->getName());

        $customers = $customerQuery->limit(2)->offset(3)->all();
        $this->assertCount(0, $customers);

        /** offset */
        $customerQuery = new ActiveQuery(CustomerWithRownumid::class);
        $customer = $customerQuery->orderBy('id')->offset(0)->one();
        $this->assertEquals('user1', $customer->getName());

        $customer = $customerQuery->orderBy('id')->offset(1)->one();
        $this->assertEquals('user2', $customer->getName());

        $customer = $customerQuery->orderBy('id')->offset(2)->one();
        $this->assertEquals('user3', $customer->getName());

        $customer = $customerQuery->offset(3)->one();
        $this->assertNull($customer);
    }

    public function testFindEager(): void
    {
        $this->checkFixture($this->db(), 'customer', true);

        $customerQuery = new ActiveQuery(Customer::class);
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

        $customer = $customerQuery->where(['id' => 1])->with('orders')->one();
        $this->assertTrue($customer->isRelationPopulated('orders'));
        $this->assertCount(1, $customer->getOrders());
        $this->assertCount(1, $customer->getRelatedRecords());

        /** multiple with() calls */
        $orderQuery = new ActiveQuery(Order::class);
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
        $this->checkFixture($this->db(), 'customer');

        /** asArray */
        $customerQuery = new ActiveQuery(Customer::class);
        $customer = $customerQuery->where(['[[id]]' => 2])->asArray()->one();
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
        $customerQuery = new ActiveQuery(Customer::class);
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
