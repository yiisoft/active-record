<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Item;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Order;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\OrderItem;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\OrderWithNullFK;
use Yiisoft\Db\Exception\InvalidCallException;

use function ksort;
use function reset;
use function time;

trait ActiveRecordTestTrait
{
    public function testFind(): void
    {
        $customerInstance = new Customer($this->db);

        /** find one */
        $result = $customerInstance->find();
        $this->assertInstanceOf(ActiveQueryInterface::class, $result);

        $customer = $result->one();
        $this->assertInstanceOf(Customer::class, $customer);

        /** find all */
        $customers = $customerInstance->find()->all();
        $this->assertCount(3, $customers);
        $this->assertInstanceOf(Customer::class, $customers[0]);
        $this->assertInstanceOf(Customer::class, $customers[1]);
        $this->assertInstanceOf(Customer::class, $customers[2]);

        /** find by a single primary key */
        $customer = $customerInstance->findOne(2);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('user2', $customer->name);

        $customer = $customerInstance->findOne(5);
        $this->assertNull($customer);

        $customer = $customerInstance->findOne(['id' => [5, 6, 1]]);
        $this->assertInstanceOf(Customer::class, $customer);

        $customer = $customerInstance->find()->where(['id' => [5, 6, 1]])->one();
        $this->assertNotNull($customer);

        /** find by column values */
        $customer = $customerInstance->findOne(['id' => 2, 'name' => 'user2']);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('user2', $customer->name);

        $customer = $customerInstance->findOne(['id' => 2, 'name' => 'user1']);
        $this->assertNull($customer);

        $customer = $customerInstance->findOne(['id' => 5]);
        $this->assertNull($customer);

        $customer = $customerInstance->findOne(['name' => 'user5']);
        $this->assertNull($customer);

        /** find by attributes */
        $customer = $customerInstance->find()->where(['name' => 'user2'])->one();
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals(2, $customer->id);

        /** scope */
        $this->assertCount(2, $customer->find()->active()->all());
        $this->assertEquals(2, $customer->find()->active()->count());
    }

    public function testFindAsArray(): void
    {
        $customerInstance = new Customer($this->db);

        /** asArray */
        $customer = $customerInstance->find()->where(['id' => 2])->asArray()->one();
        $this->assertEquals([
            'id' => 2,
            'email' => 'user2@example.com',
            'name' => 'user2',
            'address' => 'address2',
            'status' => 1,
            'profile_id' => null,
        ], $customer);

        /** find all asArray */
        $customers = $customerInstance->find()->asArray()->all();
        $this->assertCount(3, $customers);
        $this->assertArrayHasKey('id', $customers[0]);
        $this->assertArrayHasKey('name', $customers[0]);
        $this->assertArrayHasKey('email', $customers[0]);
        $this->assertArrayHasKey('address', $customers[0]);
        $this->assertArrayHasKey('status', $customers[0]);
        $this->assertArrayHasKey('id', $customers[1]);
        $this->assertArrayHasKey('name', $customers[1]);
        $this->assertArrayHasKey('email', $customers[1]);
        $this->assertArrayHasKey('address', $customers[1]);
        $this->assertArrayHasKey('status', $customers[1]);
        $this->assertArrayHasKey('id', $customers[2]);
        $this->assertArrayHasKey('name', $customers[2]);
        $this->assertArrayHasKey('email', $customers[2]);
        $this->assertArrayHasKey('address', $customers[2]);
        $this->assertArrayHasKey('status', $customers[2]);
    }

    public function testHasAttribute(): void
    {
        $customer = new Customer($this->db);

        $this->assertTrue($customer->hasAttribute('id'));
        $this->assertTrue($customer->hasAttribute('email'));
        $this->assertFalse($customer->hasAttribute(0));
        $this->assertFalse($customer->hasAttribute(null));
        $this->assertFalse($customer->hasAttribute(42));

        $customer = $customer->findOne(1);
        $this->assertTrue($customer->hasAttribute('id'));
        $this->assertTrue($customer->hasAttribute('email'));
        $this->assertFalse($customer->hasAttribute(0));
        $this->assertFalse($customer->hasAttribute(null));
        $this->assertFalse($customer->hasAttribute(42));
    }

    public function testFindScalar(): void
    {
        $customer = new Customer($this->db);

        $customerName = $customer->find()->where(['id' => 2])->scalar('name');
        $this->assertEquals('user2', $customerName);

        $customerName = $customer->find()->where(['status' => 2])->scalar('name');
        $this->assertEquals('user3', $customerName);

        $customerName = $customer->find()->where(['status' => 2])->scalar('noname');
        $this->assertNull($customerName);

        $customerId = $customer->find()->where(['status' => 2])->scalar('id');
        $this->assertEquals(3, $customerId);
    }

    public function testFindColumn(): void
    {
        $customer = new Customer($this->db);

        $this->assertEquals(
            ['user1', 'user2', 'user3'],
            $customer->find()->orderBy(['name' => SORT_ASC])->column('name')
        );
        $this->assertEquals(
            ['user3', 'user2', 'user1'],
            $customer->find()->orderBy(['name' => SORT_DESC])->column('name')
        );
    }

    public function testFindIndexBy(): void
    {
        $customer = new Customer($this->db);

        $customers = $customer->find()->indexBy('name')->orderBy('id')->all();

        $this->assertCount(3, $customers);
        $this->assertInstanceOf(Customer::class, $customers['user1']);
        $this->assertInstanceOf(Customer::class, $customers['user2']);
        $this->assertInstanceOf(Customer::class, $customers['user3']);

        /** indexBy callable */
        $customers = $customer->find()->indexBy(function (Customer $customer) {
            return $customer->id . '-' . $customer->name;
        })->orderBy('id')->all();

        $this->assertCount(3, $customers);
        $this->assertInstanceOf(Customer::class, $customers['1-user1']);
        $this->assertInstanceOf(Customer::class, $customers['2-user2']);
        $this->assertInstanceOf(Customer::class, $customers['3-user3']);
    }

    public function testFindIndexByAsArray(): void
    {
        $customer = new Customer($this->db);

        $customers = $customer->find()->asArray()->indexBy('name')->all();

        $this->assertCount(3, $customers);
        $this->assertArrayHasKey('id', $customers['user1']);
        $this->assertArrayHasKey('name', $customers['user1']);
        $this->assertArrayHasKey('email', $customers['user1']);
        $this->assertArrayHasKey('address', $customers['user1']);
        $this->assertArrayHasKey('status', $customers['user1']);
        $this->assertArrayHasKey('id', $customers['user2']);
        $this->assertArrayHasKey('name', $customers['user2']);
        $this->assertArrayHasKey('email', $customers['user2']);
        $this->assertArrayHasKey('address', $customers['user2']);
        $this->assertArrayHasKey('status', $customers['user2']);
        $this->assertArrayHasKey('id', $customers['user3']);
        $this->assertArrayHasKey('name', $customers['user3']);
        $this->assertArrayHasKey('email', $customers['user3']);
        $this->assertArrayHasKey('address', $customers['user3']);
        $this->assertArrayHasKey('status', $customers['user3']);

        /** indexBy callable + asArray */
        $customers = $customer->find()->indexBy(function ($customer) {
            return $customer['id'] . '-' . $customer['name'];
        })->asArray()->all();

        $this->assertCount(3, $customers);
        $this->assertArrayHasKey('id', $customers['1-user1']);
        $this->assertArrayHasKey('name', $customers['1-user1']);
        $this->assertArrayHasKey('email', $customers['1-user1']);
        $this->assertArrayHasKey('address', $customers['1-user1']);
        $this->assertArrayHasKey('status', $customers['1-user1']);
        $this->assertArrayHasKey('id', $customers['2-user2']);
        $this->assertArrayHasKey('name', $customers['2-user2']);
        $this->assertArrayHasKey('email', $customers['2-user2']);
        $this->assertArrayHasKey('address', $customers['2-user2']);
        $this->assertArrayHasKey('status', $customers['2-user2']);
        $this->assertArrayHasKey('id', $customers['3-user3']);
        $this->assertArrayHasKey('name', $customers['3-user3']);
        $this->assertArrayHasKey('email', $customers['3-user3']);
        $this->assertArrayHasKey('address', $customers['3-user3']);
        $this->assertArrayHasKey('status', $customers['3-user3']);
    }

    public function testRefresh(): void
    {
        $customer = new Customer($this->db);

        $this->assertFalse($customer->refresh());

        $customer = $customer->findOne(1);
        $customer->name = 'to be refreshed';

        $this->assertTrue($customer->refresh());
        $this->assertEquals('user1', $customer->name);
    }

    public function testEquals(): void
    {
        $customerA = new Customer($this->db);
        $customerB = new Customer($this->db);
        $this->assertFalse($customerA->equals($customerB));

        $customerA = new Customer($this->db);
        $customerB = new Item($this->db);
        $this->assertFalse($customerA->equals($customerB));

        $customerA = (new Customer($this->db))->findOne(1);
        $customerB = (new Customer($this->db))->findOne(2);
        $this->assertFalse($customerA->equals($customerB));

        $customerB = (new Customer($this->db))->findOne(1);
        $this->assertTrue($customerA->equals($customerB));

        $customerA = (new Customer($this->db))->findOne(1);
        $customerB = (new Item($this->db))->findOne(1);
        $this->assertFalse($customerA->equals($customerB));
    }

    public function testFindCount(): void
    {
        $customer = new Customer($this->db);

        /** @var $this TestCase|ActiveRecordTestTrait */
        $this->assertEquals(3, $customer->find()->count());

        $this->assertEquals(1, $customer->find()->where(['id' => 1])->count());
        $this->assertEquals(2, $customer->find()->where(['id' => [1, 2]])->count());
        $this->assertEquals(2, $customer->find()->where(['id' => [1, 2]])->offset(1)->count());
        $this->assertEquals(2, $customer->find()->where(['id' => [1, 2]])->offset(2)->count());

        /** limit should have no effect on count() */
        $this->assertEquals(3, $customer->find()->limit(1)->count());
        $this->assertEquals(3, $customer->find()->limit(2)->count());
        $this->assertEquals(3, $customer->find()->limit(10)->count());
        $this->assertEquals(3, $customer->find()->offset(2)->limit(2)->count());
    }

    public function testFindLimit(): void
    {
        $customerInstance = new Customer($this->db);

        $customers = $customerInstance->find()->all();
        $this->assertCount(3, $customers);

        $customers = $customerInstance->find()->orderBy('id')->limit(1)->all();
        $this->assertCount(1, $customers);
        $this->assertEquals('user1', $customers[0]->name);

        $customers = $customerInstance->find()->orderBy('id')->limit(1)->offset(1)->all();
        $this->assertCount(1, $customers);
        $this->assertEquals('user2', $customers[0]->name);

        $customers = $customerInstance->find()->orderBy('id')->limit(1)->offset(2)->all();
        $this->assertCount(1, $customers);
        $this->assertEquals('user3', $customers[0]->name);

        $customers = $customerInstance->find()->orderBy('id')->limit(2)->offset(1)->all();
        $this->assertCount(2, $customers);
        $this->assertEquals('user2', $customers[0]->name);
        $this->assertEquals('user3', $customers[1]->name);

        $customers = $customerInstance->find()->limit(2)->offset(3)->all();
        $this->assertCount(0, $customers);

        $customer = $customerInstance->find()->orderBy('id')->one();
        $this->assertEquals('user1', $customer->name);

        $customer = $customerInstance->find()->orderBy('id')->offset(0)->one();
        $this->assertEquals('user1', $customer->name);

        $customer = $customerInstance->find()->orderBy('id')->offset(1)->one();
        $this->assertEquals('user2', $customer->name);

        $customer = $customerInstance->find()->orderBy('id')->offset(2)->one();
        $this->assertEquals('user3', $customer->name);

        $customer = $customerInstance->find()->offset(3)->one();
        $this->assertNull($customer);
    }

    public function testFindComplexCondition(): void
    {
        $customer = new Customer($this->db);

        $this->assertEquals(
            2,
            $customer->find()->where(['OR', ['name' => 'user1'], ['name' => 'user2']])->count()
        );
        $this->assertCount(
            2,
            $customer->find()->where(['OR', ['name' => 'user1'], ['name' => 'user2']])->all()
        );

        $this->assertEquals(
            2,
            $customer->find()->where(['name' => ['user1', 'user2']])->count()
        );
        $this->assertCount(
            2,
            $customer->find()->where(['name' => ['user1', 'user2']])->all()
        );

        $this->assertEquals(
            1,
            $customer->find()->where(['AND', ['name' => ['user2', 'user3']], ['BETWEEN', 'status', 2, 4]])->count()
        );
        $this->assertCount(
            1,
            $customer->find()->where(['AND', ['name' => ['user2', 'user3']], ['BETWEEN', 'status', 2, 4]])->all()
        );
    }

    public function testFindNullValues(): void
    {
        $customer = new Customer($this->db);
        $customer = $customer->findOne(2);
        $customer->name = null;
        $customer->save();

        $result = $customer->find()->where(['name' => null])->all();
        $this->assertCount(1, $result);
        $this->assertEquals(2, reset($result)->primaryKey);
    }

    public function testExists(): void
    {
        $customer = new Customer($this->db);

        $this->assertTrue($customer->find()->where(['id' => 2])->exists());
        $this->assertFalse($customer->find()->where(['id' => 5])->exists());
        $this->assertTrue($customer->find()->where(['name' => 'user1'])->exists());
        $this->assertFalse($customer->find()->where(['name' => 'user5'])->exists());

        $this->assertTrue($customer->find()->where(['id' => [2, 3]])->exists());
        $this->assertTrue($customer->find()->where(['id' => [2, 3]])->offset(1)->exists());
        $this->assertFalse($customer->find()->where(['id' => [2, 3]])->offset(2)->exists());
    }

    public function testFindLazy(): void
    {
        $customerInstance = new Customer($this->db);

        $customer = $customerInstance->findOne(2);
        $this->assertFalse($customer->isRelationPopulated('orders'));

        $orders = $customer->orders;
        $this->assertTrue($customer->isRelationPopulated('orders'));
        $this->assertCount(2, $orders);
        $this->assertCount(1, $customer->relatedRecords);

        unset($customer['orders']);
        $this->assertFalse($customer->isRelationPopulated('orders'));

        $customer = $customerInstance->findOne(2);
        $this->assertFalse($customer->isRelationPopulated('orders'));

        $orders = $customer->getOrders()->where(['id' => 3])->all();
        $this->assertFalse($customer->isRelationPopulated('orders'));
        $this->assertCount(0, $customer->relatedRecords);
        $this->assertCount(1, $orders);
        $this->assertEquals(3, $orders[0]->id);
    }

    public function testFindEager(): void
    {
        $this->loadFixture($this->db);

        $customerInstance = new Customer($this->db);
        $orderInstance = new Order($this->db);

        $customers = $customerInstance->find()->with('orders')->indexBy('id')->all();

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

        $customer = $customerInstance->find()->where(['id' => 1])->with('orders')->one();
        $this->assertTrue($customer->isRelationPopulated('orders'));
        $this->assertCount(1, $customer->orders);
        $this->assertCount(1, $customer->relatedRecords);

        /** multiple with() calls */
        $orders = $orderInstance->find()->with('customer', 'items')->all();
        $this->assertCount(3, $orders);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[0]->isRelationPopulated('items'));

        $orders = $orderInstance->find()->with('customer')->with('items')->all();
        $this->assertCount(3, $orders);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
    }

    public function testFindLazyVia(): void
    {
        $orderInstance = new Order($this->db);

        /**
         * @var $this TestCase|ActiveRecordTestTrait
         * @var $order Order
         */
        $order = $orderInstance->findOne(1);

        $this->assertEquals(1, $order->id);
        $this->assertCount(2, $order->items);
        $this->assertEquals(1, $order->items[0]->id);
        $this->assertEquals(2, $order->items[1]->id);
    }

    public function testFindEagerViaRelation(): void
    {
        $orderInstance = new Order($this->db);

        $orders = $orderInstance->find()->with('items')->orderBy('id')->all();

        $this->assertCount(3, $orders);
        $order = $orders[0];

        $this->assertEquals(1, $order->id);
        $this->assertTrue($order->isRelationPopulated('items'));
        $this->assertCount(2, $order->items);
        $this->assertEquals(1, $order->items[0]->id);
        $this->assertEquals(2, $order->items[1]->id);
    }

    public function testFindNestedRelation(): void
    {
        $customer = new Customer($this->db);

        $customers = $customer->find()->with('orders', 'orders.items')->indexBy('id')->all();

        ksort($customers);
        $this->assertCount(3, $customers);
        $this->assertTrue($customers[1]->isRelationPopulated('orders'));
        $this->assertTrue($customers[2]->isRelationPopulated('orders'));
        $this->assertTrue($customers[3]->isRelationPopulated('orders'));
        $this->assertCount(1, $customers[1]->orders);
        $this->assertCount(2, $customers[2]->orders);
        $this->assertCount(0, $customers[3]->orders);
        $this->assertTrue($customers[1]->orders[0]->isRelationPopulated('items'));
        $this->assertTrue($customers[2]->orders[0]->isRelationPopulated('items'));
        $this->assertTrue($customers[2]->orders[1]->isRelationPopulated('items'));
        $this->assertCount(2, $customers[1]->orders[0]->items);
        $this->assertCount(3, $customers[2]->orders[0]->items);
        $this->assertCount(1, $customers[2]->orders[1]->items);

        $customers = $customer->find()->where(['id' => 1])->with('ordersWithItems')->one();
        $this->assertTrue($customers->isRelationPopulated('ordersWithItems'));
        $this->assertCount(1, $customers->ordersWithItems);

        $order = $customers->ordersWithItems[0];
        $this->assertTrue($order->isRelationPopulated('orderItems'));
        $this->assertCount(2, $order->orderItems);
    }

    /**
     * Ensure ActiveRelationTrait does preserve order of items on find via().
     *
     * {@see https://github.com/yiisoft/yii2/issues/1310.}
     */
    public function testFindEagerViaRelationPreserveOrder(): void
    {
        $order = new Order($this->db);

        $orders = $order->find()->with('itemsInOrder1')->orderBy('created_at')->all();
        $this->assertCount(3, $orders);

        $order = $orders[0];
        $this->assertEquals(1, $order->id);
        $this->assertTrue($order->isRelationPopulated('itemsInOrder1'));
        $this->assertCount(2, $order->itemsInOrder1);
        $this->assertEquals(1, $order->itemsInOrder1[0]->id);
        $this->assertEquals(2, $order->itemsInOrder1[1]->id);

        $order = $orders[1];
        $this->assertEquals(2, $order->id);
        $this->assertTrue($order->isRelationPopulated('itemsInOrder1'));
        $this->assertCount(3, $order->itemsInOrder1);
        $this->assertEquals(5, $order->itemsInOrder1[0]->id);
        $this->assertEquals(3, $order->itemsInOrder1[1]->id);
        $this->assertEquals(4, $order->itemsInOrder1[2]->id);

        $order = $orders[2];
        $this->assertEquals(3, $order->id);
        $this->assertTrue($order->isRelationPopulated('itemsInOrder1'));
        $this->assertCount(1, $order->itemsInOrder1);
        $this->assertEquals(2, $order->itemsInOrder1[0]->id);
    }

    public function testFindEagerViaRelationPreserveOrderB(): void
    {
        $order = new Order($this->db);

        /**
         *  different order in via table.
         *
         *  @var Order \yii\db\ActiveRecordInterface
         */
        $orders = $order->find()->with('itemsInOrder2')->orderBy('created_at')->all();
        $this->assertCount(3, $orders);

        $order = $orders[0];
        $this->assertEquals(1, $order->id);
        $this->assertTrue($order->isRelationPopulated('itemsInOrder2'));
        $this->assertCount(2, $order->itemsInOrder2);
        $this->assertEquals(1, $order->itemsInOrder2[0]->id);
        $this->assertEquals(2, $order->itemsInOrder2[1]->id);

        $order = $orders[1];
        $this->assertEquals(2, $order->id);
        $this->assertTrue($order->isRelationPopulated('itemsInOrder2'));
        $this->assertCount(3, $order->itemsInOrder2);
        $this->assertEquals(5, $order->itemsInOrder2[0]->id);
        $this->assertEquals(3, $order->itemsInOrder2[1]->id);
        $this->assertEquals(4, $order->itemsInOrder2[2]->id);

        $order = $orders[2];
        $this->assertEquals(3, $order->id);
        $this->assertTrue($order->isRelationPopulated('itemsInOrder2'));
        $this->assertCount(1, $order->itemsInOrder2);
        $this->assertEquals(2, $order->itemsInOrder2[0]->id);
    }

    public function testUnlink(): void
    {
        $this->loadFixture($this->db);

        $customerInstance = new Customer($this->db);
        $orderInstance = new Order($this->db);
        $orderWithNullFKInstance = new OrderWithNullFK($this->db);

        /**
         * has many without delete
         * @var $this TestCase|ActiveRecordTestTrait
         */
        $customer = $customerInstance->findOne(2);
        $this->assertCount(2, $customer->ordersWithNullFK);
        $customer->unlink('ordersWithNullFK', $customer->ordersWithNullFK[1], false);
        $this->assertCount(1, $customer->ordersWithNullFK);

        $orderWithNullFK = $orderWithNullFKInstance->findOne(3);
        $this->assertEquals(3, $orderWithNullFK->id);
        $this->assertNull($orderWithNullFK->customer_id);

        /** has many with delete */
        $customer = $customerInstance->findOne(2);
        $this->assertCount(2, $customer->orders);
        $customer->unlink('orders', $customer->orders[1], true);
        $this->assertCount(1, $customer->orders);
        $this->assertNull($orderInstance->findOne(3));

        /** via model with delete */
        $order = $orderInstance->findOne(2);
        $this->assertCount(3, $order->items);
        $this->assertCount(3, $order->orderItems);
        $order->unlink('items', $order->items[2], true);
        $this->assertCount(2, $order->items);
        $this->assertCount(2, $order->orderItems);

        /** via model without delete */
        $this->assertCount(3, $order->itemsWithNullFK);
        $order->unlink('itemsWithNullFK', $order->itemsWithNullFK[2], false);
        $this->assertCount(2, $order->itemsWithNullFK);
        $this->assertCount(2, $order->orderItems);
    }

    public function testUnlinkAllAndConditionSetNull(): void
    {
        $customerInstance = new Customer($this->db);
        $orderWithNullFKInstance = new OrderWithNullFK($this->db);

        /** in this test all orders are owned by customer 1 */
        $orderWithNullFKInstance->updateAll(['customer_id' => 1]);

        $customer = $customerInstance->findOne(1);
        $this->assertCount(3, $customer->ordersWithNullFK);
        $this->assertCount(1, $customer->expensiveOrdersWithNullFK);
        $this->assertEquals(3, $orderWithNullFKInstance->find()->count());

        $customer->unlinkAll('expensiveOrdersWithNullFK');
        $this->assertCount(3, $customer->ordersWithNullFK);
        $this->assertCount(0, $customer->expensiveOrdersWithNullFK);
        $this->assertEquals(3, $orderWithNullFKInstance->find()->count());

        $customer =  $customerInstance->findOne(1);
        $this->assertCount(2, $customer->ordersWithNullFK);
        $this->assertCount(0, $customer->expensiveOrdersWithNullFK);
    }

    public function testUnlinkAllAndConditionDelete(): void
    {
        $this->loadFixture($this->db);

        $customerInstance = new Customer($this->db);
        $orderInstance = new Order($this->db);

        /** in this test all orders are owned by customer 1 */
        $orderInstance->updateAll(['customer_id' => 1]);

        $customer = $customerInstance->findOne(1);
        $this->assertCount(3, $customer->orders);
        $this->assertCount(1, $customer->expensiveOrders);
        $this->assertEquals(3, $orderInstance->find()->count());

        $customer->unlinkAll('expensiveOrders', true);
        $this->assertCount(3, $customer->orders);
        $this->assertCount(0, $customer->expensiveOrders);
        $this->assertEquals(2, $orderInstance->find()->count());

        $customer = $customerInstance->findOne(1);
        $this->assertCount(2, $customer->orders);
        $this->assertCount(0, $customer->expensiveOrders);
    }

    public function testInsert(): void
    {
        /** @var $this TestCase|ActiveRecordTestTrait */
        $customer = new Customer($this->db);

        $customer->email = 'user4@example.com';
        $customer->name = 'user4';
        $customer->address = 'address4';

        $this->assertNull($customer->id);
        $this->assertTrue($customer->isNewRecord);

        $customer->save();

        $this->assertNotNull($customer->id);
        $this->assertFalse($customer->isNewRecord);
    }

    public function testUpdate()
    {
        $this->loadFixture($this->db);

        $customerInstance = new Customer($this->db);

        $customer = $customerInstance->findOne(2);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('user2', $customer->name);
        $this->assertFalse($customer->isNewRecord);
        $this->assertEmpty($customer->dirtyAttributes);

        $customer->name = 'user2x';
        $customer->save();

        $this->assertEquals('user2x', $customer->name);
        $this->assertFalse($customer->isNewRecord);

        $customer2 = $customerInstance->findOne(2);
        $this->assertEquals('user2x', $customer2->name);

        /** updateAll */
        $customer = $customerInstance->findOne(3);
        $this->assertEquals('user3', $customer->name);

        $ret = $customerInstance->updateAll(['name' => 'temp'], ['id' => 3]);
        $this->assertEquals(1, $ret);

        $customer = $customerInstance->findOne(3);
        $this->assertEquals('temp', $customer->name);

        $ret = $customerInstance->updateAll(['name' => 'tempX']);
        $this->assertEquals(3, $ret);

        $ret = $customerInstance->updateAll(['name' => 'temp'], ['name' => 'user6']);
        $this->assertEquals(0, $ret);
    }

    public function testUpdateAttributes(): void
    {
        $this->loadFixture($this->db);

        $customerInstance = new Customer($this->db);

        /** @var $customer Customer */
        $customer = $customerInstance->findOne(2);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('user2', $customer->name);
        $this->assertFalse($customer->isNewRecord);

        $customer->updateAttributes(['name' => 'user2x']);
        $this->assertEquals('user2x', $customer->name);
        $this->assertFalse($customer->isNewRecord);

        $customer2 = $customerInstance->findOne(2);
        $this->assertEquals('user2x', $customer2->name);

        $customer = $customerInstance->findOne(1);
        $this->assertEquals('user1', $customer->name);
        $this->assertEquals(1, $customer->status);

        $customer->name = 'user1x';
        $customer->status = 2;
        $customer->updateAttributes(['name']);
        $this->assertEquals('user1x', $customer->name);
        $this->assertEquals(2, $customer->status);

        $customer = $customerInstance->findOne(1);
        $this->assertEquals('user1x', $customer->name);
        $this->assertEquals(1, $customer->status);
    }

    public function testUpdateCounters(): void
    {
        $this->loadFixture($this->db);

        $orderItemInstance = new OrderItem($this->db);

        /** updateCounters */
        $pk = ['order_id' => 2, 'item_id' => 4];
        $orderItem = $orderItemInstance->findOne($pk);
        $this->assertEquals(1, $orderItem->quantity);

        $ret = $orderItem->updateCounters(['quantity' => -1]);
        $this->assertEquals(1, $ret);
        $this->assertEquals(0, $orderItem->quantity);

        $orderItem = $orderItemInstance->findOne($pk);
        $this->assertEquals(0, $orderItem->quantity);

        /** updateAllCounters */
        $pk = ['order_id' => 1, 'item_id' => 2];
        $orderItem = $orderItemInstance->findOne($pk);
        $this->assertEquals(2, $orderItem->quantity);

        $ret = $orderItemInstance->updateAllCounters(['quantity' => 3, 'subtotal' => -10], $pk);
        $this->assertEquals(1, $ret);

        $orderItem = $orderItemInstance->findOne($pk);
        $this->assertEquals(5, $orderItem->quantity);
        $this->assertEquals(30, $orderItem->subtotal);
    }

    public function testDelete(): void
    {
        $customerInstance = new Customer($this->db);

        /** delete */
        $customer = $customerInstance->findOne(2);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('user2', $customer->name);

        $customer->delete();

        $customer = $customerInstance->findOne(2);
        $this->assertNull($customer);

        /** deleteAll */
        $customers = $customerInstance->find()->all();
        $this->assertCount(2, $customers);

        $ret = $customerInstance->deleteAll();
        $this->assertEquals(2, $ret);

        $customers = $customerInstance->find()->all();
        $this->assertCount(0, $customers);

        $ret = $customerInstance->deleteAll();
        $this->assertEquals(0, $ret);
    }

    /**
     * Some PDO implementations (e.g. cubrid) do not support boolean values.
     *
     * Make sure this does not affect AR layer.
     */
    public function testBooleanAttribute(): void
    {
        $this->loadFixture($this->db);

        /** @var $this TestCase|ActiveRecordTestTrait */
        $customer = new Customer($this->db);

        $customer->name = 'boolean customer';
        $customer->email = 'mail@example.com';
        $customer->status = true;

        $customer->save();
        $customer->refresh();
        $this->assertEquals(1, $customer->status);

        $customer->status = false;
        $customer->save();

        $customer->refresh();
        $this->assertEquals(0, $customer->status);

        $customers = $customer->find()->where(['status' => true])->all();
        $this->assertCount(2, $customers);

        $customers = $customer->find()->where(['status' => false])->all();
        $this->assertCount(1, $customers);
    }

    public function testFindEmptyInCondition(): void
    {
        $this->loadFixture($this->db);

        $customer = new Customer($this->db);

        /** @var $this TestCase|ActiveRecordTestTrait */
        $customers = $customer->find()->where(['id' => [1]])->all();
        $this->assertCount(1, $customers);

        $customers = $customer->find()->where(['id' => []])->all();
        $this->assertCount(0, $customers);

        $customers = $customer->find()->where(['IN', 'id', [1]])->all();
        $this->assertCount(1, $customers);

        $customers = $customer->find()->where(['IN', 'id', []])->all();
        $this->assertCount(0, $customers);
    }

    public function testFindEagerIndexBy(): void
    {
        $this->loadFixture($this->db);

        $orderInstance = new Order($this->db);

        /** @var $order Order */
        $order = $orderInstance->find()->with('itemsIndexed')->where(['id' => 1])->one();
        $this->assertTrue($order->isRelationPopulated('itemsIndexed'));

        $items = $order->itemsIndexed;
        $this->assertCount(2, $items);
        $this->assertTrue(isset($items[1]));
        $this->assertTrue(isset($items[2]));

        /** @var $order Order */
        $order = $orderInstance->find()->with('itemsIndexed')->where(['id' => 2])->one();
        $this->assertTrue($order->isRelationPopulated('itemsIndexed'));

        $items = $order->itemsIndexed;
        $this->assertCount(3, $items);
        $this->assertTrue(isset($items[3]));
        $this->assertTrue(isset($items[4]));
        $this->assertTrue(isset($items[5]));
    }

    public function testAttributeAccess(): void
    {
        $arClass = new Customer($this->db);

        $this->assertTrue($arClass->canSetProperty('name'));
        $this->assertTrue($arClass->canGetProperty('name'));
        $this->assertFalse($arClass->canSetProperty('unExistingColumn'));
        $this->assertFalse(isset($arClass->name));

        $arClass->name = 'foo';
        $this->assertTrue(isset($arClass->name));

        unset($arClass->name);
        $this->assertNull($arClass->name);

        /** {@see https://github.com/yiisoft/yii2-gii/issues/190} */
        $baseModel = new Customer($this->db);
        $this->assertFalse($baseModel->hasProperty('unExistingColumn'));

        $customer = new Customer($this->db);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertTrue($customer->canGetProperty('id'));
        $this->assertTrue($customer->canSetProperty('id'));

        /** tests that we really can get and set this property */
        $this->assertNull($customer->id);
        $customer->id = 10;
        $this->assertNotNull($customer->id);

        /** Let's test relations */
        $this->assertTrue($customer->canGetProperty('orderItems'));
        $this->assertFalse($customer->canSetProperty('orderItems'));

        /** Newly created model must have empty relation */
        $this->assertSame([], $customer->orderItems);

        /** does it still work after accessing the relation? */
        $this->assertTrue($customer->canGetProperty('orderItems'));
        $this->assertFalse($customer->canSetProperty('orderItems'));

        try {
            /** @var $itemClass ActiveRecordInterface */
            $customer->orderItems = [new Item($this->db)];
            $this->fail('setter call above MUST throw Exception');
        } catch (\Exception $e) {
            /** catch exception "Setting read-only property" */
            $this->assertInstanceOf(InvalidCallException::class, $e);
        }

        /** related attribute $customer->orderItems didn't change cause it's read-only */
        $this->assertSame([], $customer->orderItems);
        $this->assertFalse($customer->canGetProperty('non_existing_property'));
        $this->assertFalse($customer->canSetProperty('non_existing_property'));
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/17089}
     */
    public function testViaWithCallable(): void
    {
        $this->loadFixture($this->db);

        $orderInstance = new Order($this->db);

        $order = $orderInstance->findOne(2);

        $expensiveItems = $order->expensiveItemsUsingViaWithCallable;
        $cheapItems = $order->cheapItemsUsingViaWithCallable;

        $this->assertCount(2, $expensiveItems);
        $this->assertEquals(4, $expensiveItems[0]->id);
        $this->assertEquals(5, $expensiveItems[1]->id);
        $this->assertCount(1, $cheapItems);
        $this->assertEquals(3, $cheapItems[0]->id);
    }

    public function testLink()
    {
        $this->loadFixture($this->db);

        $customerInstance = new Customer($this->db);

        $customer = $customerInstance->findOne(2);
        $this->assertCount(2, $customer->orders);

        /** has many */
        $order = new Order($this->db);

        $order->total = 100;
        $order->created_at = time();
        $this->assertTrue($order->isNewRecord);

        /** belongs to */
        $order = new Order($this->db);

        $order->total = 100;
        $order->created_at = time();
        $this->assertTrue($order->isNewRecord);

        $customer = $customerInstance->findOne(1);
        $this->assertNull($order->customer);

        $order->link('customer', $customer);
        $this->assertFalse($order->isNewRecord);
        $this->assertEquals(1, $order->customer_id);
        $this->assertEquals(1, $order->customer->primaryKey);

        /** via model */
        $order = $order->findOne(1);
        $this->assertCount(2, $order->items);
        $this->assertCount(2, $order->orderItems);

        $orderItemInstance = new OrderItem($this->db);

        $orderItem = $orderItemInstance->findOne(['order_id' => 1, 'item_id' => 3]);
        $this->assertNull($orderItem);

        $itemInstance = new Item($this->db);

        $item = $itemInstance->findOne(3);
        $order->link('items', $item, ['quantity' => 10, 'subtotal' => 100]);
        $this->assertCount(3, $order->items);
        $this->assertCount(3, $order->orderItems);

        $orderItem = $orderItemInstance->findOne(['order_id' => 1, 'item_id' => 3]);
        $this->assertInstanceOf(OrderItem::class, $orderItem);
        $this->assertEquals(10, $orderItem->quantity);
        $this->assertEquals(100, $orderItem->subtotal);
    }
}
