<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Order;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\OrderItem;

use function ksort;

abstract class ActiveQueryFindTest extends TestCase
{
    public function testFindAll(): void
    {
        $this->checkFixture($this->db(), 'customer', true);

        $customerQuery = new ActiveQuery(Customer::class);
        $this->assertCount(1, $customerQuery->findAll(3));

        $customerQuery = new ActiveQuery(Customer::class);
        $this->assertCount(1, $customerQuery->findAll(['id' => 1]));

        $customerQuery = new ActiveQuery(Customer::class);
        $this->assertCount(3, $customerQuery->findAll(['id' => [1, 2, 3]]));
    }

    public function testFindScalar(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerQuery = new ActiveQuery(Customer::class);

        /** query scalar */
        $customerName = $customerQuery->where(['[[id]]' => 2])->select('[[name]]')->scalar();

        $this->assertEquals('user2', $customerName);
    }

    public function testFindExists(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerQuery = new ActiveQuery(Customer::class);

        $this->assertTrue($customerQuery->where(['[[id]]' => 2])->exists());
        $this->assertTrue($customerQuery->where(['[[id]]' => 2])->select('[[name]]')->exists());

        $this->assertFalse($customerQuery->where(['[[id]]' => 42])->exists());
        $this->assertFalse($customerQuery->where(['[[id]]' => 42])->select('[[name]]')->exists());
    }

    public function testFindColumn(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerQuery = new ActiveQuery(Customer::class);

        $this->assertEquals(
            ['user1', 'user2', 'user3'],
            $customerQuery->select('[[name]]')->column()
        );

        $this->assertEquals(
            ['user3', 'user2', 'user1'],
            $customerQuery->orderBy(['[[name]]' => SORT_DESC])->select('[[name]]')->column()
        );
    }

    public function testFindBySql(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerQuery = new ActiveQuery(Customer::class);

        /** find one() */
        $customers = $customerQuery->findBySql('SELECT * FROM {{customer}} ORDER BY [[id]] DESC')->one();
        $this->assertInstanceOf(Customer::class, $customers);
        $this->assertEquals('user3', $customers->get('name'));

        /** find all() */
        $customers = $customerQuery->findBySql('SELECT * FROM {{customer}}')->all();
        $this->assertCount(3, $customers);

        /** find with parameter binding */
        $customers = $customerQuery
            ->findBySql('SELECT * FROM {{customer}} WHERE [[id]]=:id', [':id' => 2])
            ->one();
        $this->assertInstanceOf(Customer::class, $customers);
        $this->assertEquals('user2', $customers->get('name'));
    }

    public function testFindLazyViaTable(): void
    {
        $this->checkFixture($this->db(), 'order');

        $orderQuery = new ActiveQuery(Order::class);

        $orders = $orderQuery->findOne(2);
        $this->assertCount(0, $orders->getBooks());
        $this->assertEquals(2, $orders->get('id'));

        $orders = $orderQuery->where(['id' => 1])->asArray()->one();
        $this->assertIsArray($orders);
    }

    public function testFindEagerViaTable(): void
    {
        $this->checkFixture($this->db(), 'order');

        $orderQuery = new ActiveQuery(Order::class);
        $orders = $orderQuery->with('books')->orderBy('id')->all();
        $this->assertCount(3, $orders);

        $order = $orders[0];
        $this->assertCount(2, $order->getBooks());
        $this->assertEquals(1, $order->get('id'));
        $this->assertEquals(1, $order->getBooks()[0]->get('id'));
        $this->assertEquals(2, $order->getBooks()[1]->get('id'));

        $order = $orders[1];
        $this->assertCount(0, $order->getBooks());
        $this->assertEquals(2, $order->get('id'));

        $order = $orders[2];
        $this->assertCount(1, $order->getBooks());
        $this->assertEquals(3, $order->get('id'));
        $this->assertEquals(2, $order->getBooks()[0]->get('id'));

        /** https://github.com/yiisoft/yii2/issues/1402 */
        $orderQuery = new ActiveQuery(Order::class);
        $orders = $orderQuery->with('books')->orderBy('id')->asArray()->all();
        $this->assertCount(3, $orders);
        $this->assertIsArray($orders[0]['orderItems'][0]);

        $order = $orders[0];
        $this->assertCount(2, $order['books']);
        $this->assertEquals(1, $order['id']);
        $this->assertEquals(1, $order['books'][0]['id']);
        $this->assertEquals(2, $order['books'][1]['id']);
        $this->assertIsArray($order);
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/10201}
     * {@see https://github.com/yiisoft/yii2/issues/9047}
     */
    public function testFindCompositeRelationWithJoin(): void
    {
        $this->checkFixture($this->db(), 'order_item');

        $orderItemQuery = new ActiveQuery(OrderItem::class);

        /** @var $orderItems OrderItem */
        $orderItems = $orderItemQuery->findOne([1, 1]);

        $orderItemNoJoin = $orderItems->getOrderItemCompositeNoJoin();
        $this->assertInstanceOf(OrderItem::class, $orderItemNoJoin);

        $orderItemWithJoin = $orderItems->getOrderItemCompositeWithJoin();
        $this->assertInstanceOf(OrderItem::class, $orderItemWithJoin);
    }

    public function testFindSimpleRelationWithJoin(): void
    {
        $this->checkFixture($this->db(), 'order');

        $orderQuery = new ActiveQuery(Order::class);

        $orders = $orderQuery->findOne(1);
        $customerNoJoin = $orders->getCustomer();
        $this->assertInstanceOf(Customer::class, $customerNoJoin);

        $customerWithJoin = $orders->getCustomerJoinedWithProfile();
        $this->assertInstanceOf(Customer::class, $customerWithJoin);

        $customerWithJoinIndexOrdered = $orders->getCustomerJoinedWithProfileIndexOrdered();
        $this->assertArrayHasKey('user1', $customerWithJoinIndexOrdered);
        $this->assertInstanceOf(Customer::class, $customerWithJoinIndexOrdered['user1']);
        $this->assertIsArray($customerWithJoinIndexOrdered);
    }

    public function testFindOneByColumnName(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customer = new ActiveQuery(Customer::class);
        $customerQuery = new CustomerQuery(Customer::class);

        $arClass = $customer->findOne(['id' => 1]);
        $this->assertEquals(1, $arClass->getId());

        $customerQuery->joinWithProfile = true;

        $arClass = $customer->findOne(['customer.id' => 1]);
        $this->assertEquals(1, $arClass->getId());

        $customerQuery->joinWithProfile = false;
    }

    public function testFind(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerQuery = new ActiveQuery(Customer::class);
        $this->assertInstanceOf(ActiveQueryInterface::class, $customerQuery);

        /** find one */
        $customer = $customerQuery->one();
        $this->assertInstanceOf(Customer::class, $customer);

        /** find all */
        $customerQuery = new ActiveQuery(Customer::class);
        $customers = $customerQuery->all();
        $this->assertCount(3, $customers);
        $this->assertInstanceOf(Customer::class, $customers[0]);
        $this->assertInstanceOf(Customer::class, $customers[1]);
        $this->assertInstanceOf(Customer::class, $customers[2]);

        /** find by a single primary key */
        $customerQuery = new ActiveQuery(Customer::class);
        $customer = $customerQuery->findOne(2);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('user2', $customer->getName());

        $customer = $customerQuery->findOne(5);
        $this->assertNull($customer);

        $customerQuery = new ActiveQuery(Customer::class);
        $customer = $customerQuery->findOne(['id' => [5, 6, 1]]);
        $this->assertInstanceOf(Customer::class, $customer);

        $customer = $customerQuery->where(['id' => [5, 6, 1]])->one();
        $this->assertNotNull($customer);

        /** find by column values */
        $customerQuery = new ActiveQuery(Customer::class);
        $customer = $customerQuery->findOne(['id' => 2, 'name' => 'user2']);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('user2', $customer->getName());

        $customer = $customerQuery->findOne(['id' => 2, 'name' => 'user1']);
        $this->assertNull($customer);

        $customer = $customerQuery->findOne(['id' => 5]);
        $this->assertNull($customer);

        $customer = $customerQuery->findOne(['name' => 'user5']);
        $this->assertNull($customer);

        /** find by column */
        $customerQuery = new ActiveQuery(Customer::class);
        $customer = $customerQuery->where(['name' => 'user2'])->one();
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals(2, $customer->getId());

        /** scope */
        $customerQuery = new CustomerQuery(Customer::class);
        $this->assertCount(2, $customerQuery->active()->all());
        $this->assertEquals(2, $customerQuery->active()->count());
    }

    public function testFindAsArray(): void
    {
        $this->checkFixture($this->db(), 'customer');

        /** asArray */
        $customerQuery = new ActiveQuery(Customer::class);
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
        $customerQuery = new ActiveQuery(Customer::class);
        $customers = $customerQuery->asArray()->all();
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

    public function testFindIndexBy(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerQuery = new ActiveQuery(Customer::class);

        $customers = $customerQuery->indexBy('name')->orderBy('id')->all();

        $this->assertCount(3, $customers);
        $this->assertInstanceOf(Customer::class, $customers['user1']);
        $this->assertInstanceOf(Customer::class, $customers['user2']);
        $this->assertInstanceOf(Customer::class, $customers['user3']);

        /** indexBy callable */
        $customer = new ActiveQuery(Customer::class);

        $customers = $customer
            ->indexBy(fn (Customer $customer) => $customer->getId() . '-' . $customer->getName())
            ->orderBy('id')
            ->all();

        $this->assertCount(3, $customers);
        $this->assertInstanceOf(Customer::class, $customers['1-user1']);
        $this->assertInstanceOf(Customer::class, $customers['2-user2']);
        $this->assertInstanceOf(Customer::class, $customers['3-user3']);
    }

    public function testFindIndexByAsArray(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerQuery = new ActiveQuery(Customer::class);
        $customers = $customerQuery->asArray()->indexBy('name')->all();
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
        $customerQuery = new ActiveQuery(Customer::class);
        $customers = $customerQuery->indexBy(fn ($customer) => $customer['id'] . '-' . $customer['name'])->asArray()->all();
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

    public function testFindCount(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerQuery = new ActiveQuery(Customer::class);
        $this->assertEquals(3, $customerQuery->count());
        $this->assertEquals(1, $customerQuery->where(['id' => 1])->count());
        $this->assertEquals(2, $customerQuery->where(['id' => [1, 2]])->count());
        $this->assertEquals(2, $customerQuery->where(['id' => [1, 2]])->offset(1)->count());
        $this->assertEquals(2, $customerQuery->where(['id' => [1, 2]])->offset(2)->count());

        $customerQuery = new ActiveQuery(Customer::class);
        $this->assertEquals(3, $customerQuery->limit(1)->count());
        $this->assertEquals(3, $customerQuery->limit(2)->count());
        $this->assertEquals(3, $customerQuery->limit(10)->count());
        $this->assertEquals(3, $customerQuery->offset(2)->limit(2)->count());
    }

    public function testFindLimit(): void
    {
        $this->checkFixture($this->db(), 'customer');

        /** one */
        $customerQuery = new ActiveQuery(Customer::class);
        $customer = $customerQuery->orderBy('id')->one();
        $this->assertEquals('user1', $customer->getName());

        /** all */
        $customerQuery = new ActiveQuery(Customer::class);
        $customers = $customerQuery->all();
        $this->assertCount(3, $customers);

        /** limit */
        $customerQuery = new ActiveQuery(Customer::class);
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
        $customerQuery = new ActiveQuery(Customer::class);
        $customer = $customerQuery->orderBy('id')->offset(0)->one();
        $this->assertEquals('user1', $customer->getName());

        $customer = $customerQuery->orderBy('id')->offset(1)->one();
        $this->assertEquals('user2', $customer->getName());

        $customer = $customerQuery->orderBy('id')->offset(2)->one();
        $this->assertEquals('user3', $customer->getName());

        $customer = $customerQuery->offset(3)->one();
        $this->assertNull($customer);
    }

    public function testFindComplexCondition(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerQuery = new ActiveQuery(Customer::class);

        $this->assertEquals(
            2,
            $customerQuery->where(['OR', ['name' => 'user1'], ['name' => 'user2']])->count()
        );

        $this->assertCount(
            2,
            $customerQuery->where(['OR', ['name' => 'user1'], ['name' => 'user2']])->all()
        );

        $this->assertEquals(
            2,
            $customerQuery->where(['name' => ['user1', 'user2']])->count()
        );

        $this->assertCount(
            2,
            $customerQuery->where(['name' => ['user1', 'user2']])->all()
        );

        $this->assertEquals(
            1,
            $customerQuery->where(['AND', ['name' => ['user2', 'user3']], ['BETWEEN', 'status', 2, 4]])->count()
        );

        $this->assertCount(
            1,
            $customerQuery->where(['AND', ['name' => ['user2', 'user3']], ['BETWEEN', 'status', 2, 4]])->all()
        );
    }

    public function testFindNullValues(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerQuery = new ActiveQuery(Customer::class);

        $customer = $customerQuery->findOne(2);
        $customer->setName(null);
        $customer->save();

        $result = $customerQuery->where(['name' => null])->all();
        $this->assertCount(1, $result);
        $this->assertEquals(2, reset($result)->getPrimaryKey());
    }

    public function testFindEager(): void
    {
        $this->checkFixture($this->db(), 'customer');

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

    public function testFindEagerViaRelation(): void
    {
        $this->checkFixture($this->db(), 'order');

        $orderQuery = new ActiveQuery(Order::class);
        $orders = $orderQuery->with('items')->orderBy('id')->all();
        $this->assertCount(3, $orders);

        $order = $orders[0];
        $this->assertEquals(1, $order->getId());
        $this->assertTrue($order->isRelationPopulated('items'));
        $this->assertCount(2, $order->getItems());
        $this->assertEquals(1, $order->getItems()[0]->getId());
        $this->assertEquals(2, $order->getItems()[1]->getId());
    }

    public function testFindNestedRelation(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerQuery = new ActiveQuery(Customer::class);
        $customers = $customerQuery->with('orders', 'orders.items')->indexBy('id')->all();

        ksort($customers);
        $this->assertCount(3, $customers);
        $this->assertTrue($customers[1]->isRelationPopulated('orders'));
        $this->assertTrue($customers[2]->isRelationPopulated('orders'));
        $this->assertTrue($customers[3]->isRelationPopulated('orders'));
        $this->assertCount(1, $customers[1]->getOrders());
        $this->assertCount(2, $customers[2]->getOrders());
        $this->assertCount(0, $customers[3]->getOrders());
        $this->assertTrue($customers[1]->getOrders()[0]->isRelationPopulated('items'));
        $this->assertTrue($customers[2]->getOrders()[0]->isRelationPopulated('items'));
        $this->assertTrue($customers[2]->getOrders()[1]->isRelationPopulated('items'));
        $this->assertCount(2, $customers[1]->getOrders()[0]->getItems());
        $this->assertCount(3, $customers[2]->getOrders()[0]->getItems());
        $this->assertCount(1, $customers[2]->getOrders()[1]->getItems());

        $customers = $customerQuery->where(['id' => 1])->with('ordersWithItems')->one();
        $this->assertTrue($customers->isRelationPopulated('ordersWithItems'));
        $this->assertCount(1, $customers->getOrdersWithItems());

        $order = $customers->getOrdersWithItems()[0];
        $this->assertTrue($order->isRelationPopulated('orderItems'));
        $this->assertCount(2, $order->getOrderItems());
    }

    /**
     * Ensure ActiveRelationTrait does preserve order of items on find via().
     *
     * {@see https://github.com/yiisoft/yii2/issues/1310.}
     */
    public function testFindEagerViaRelationPreserveOrder(): void
    {
        $this->checkFixture($this->db(), 'order');

        $orderQuery = new ActiveQuery(Order::class);
        $orders = $orderQuery->with('itemsInOrder1')->orderBy('created_at')->all();
        $this->assertCount(3, $orders);

        $order = $orders[0];
        $this->assertEquals(1, $order->getId());
        $this->assertTrue($order->isRelationPopulated('itemsInOrder1'));
        $this->assertCount(2, $order->getItemsInOrder1());
        $this->assertEquals(1, $order->getItemsInOrder1()[0]->getId());
        $this->assertEquals(2, $order->getItemsInOrder1()[1]->getId());

        $order = $orders[1];
        $this->assertEquals(2, $order->getId());
        $this->assertTrue($order->isRelationPopulated('itemsInOrder1'));
        $this->assertCount(3, $order->getItemsInOrder1());
        $this->assertEquals(5, $order->getItemsInOrder1()[0]->getId());
        $this->assertEquals(3, $order->getItemsInOrder1()[1]->getId());
        $this->assertEquals(4, $order->getItemsInOrder1()[2]->getId());

        $order = $orders[2];
        $this->assertEquals(3, $order->getId());
        $this->assertTrue($order->isRelationPopulated('itemsInOrder1'));
        $this->assertCount(1, $order->getItemsInOrder1());
        $this->assertEquals(2, $order->getItemsInOrder1()[0]->getId());
    }

    public function testFindEagerViaRelationPreserveOrderB(): void
    {
        $this->checkFixture($this->db(), 'order');

        /** different order in via table. */
        $orderQuery = new ActiveQuery(Order::class);
        $orders = $orderQuery->with('itemsInOrder2')->orderBy('created_at')->all();
        $this->assertCount(3, $orders);

        $order = $orders[0];
        $this->assertEquals(1, $order->getId());
        $this->assertTrue($order->isRelationPopulated('itemsInOrder2'));
        $this->assertCount(2, $order->getItemsInOrder2());
        $this->assertEquals(1, $order->getItemsInOrder2()[0]->getId());
        $this->assertEquals(2, $order->getItemsInOrder2()[1]->getId());

        $order = $orders[1];
        $this->assertEquals(2, $order->getId());
        $this->assertTrue($order->isRelationPopulated('itemsInOrder2'));
        $this->assertCount(3, $order->getItemsInOrder2());
        $this->assertEquals(5, $order->getItemsInOrder2()[0]->getId());
        $this->assertEquals(3, $order->getItemsInOrder2()[1]->getId());
        $this->assertEquals(4, $order->getItemsInOrder2()[2]->getId());

        $order = $orders[2];
        $this->assertEquals(3, $order->getId());
        $this->assertTrue($order->isRelationPopulated('itemsInOrder2'));
        $this->assertCount(1, $order->getItemsInOrder2());
        $this->assertEquals(2, $order->getItemsInOrder2()[0]->getId());
    }

    public function testFindEmptyInCondition(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerQuery = new ActiveQuery(Customer::class);
        $customers = $customerQuery->where(['id' => [1]])->all();
        $this->assertCount(1, $customers);

        $customers = $customerQuery->where(['id' => []])->all();
        $this->assertCount(0, $customers);

        $customers = $customerQuery->where(['IN', 'id', [1]])->all();
        $this->assertCount(1, $customers);

        $customers = $customerQuery->where(['IN', 'id', []])->all();
        $this->assertCount(0, $customers);
    }

    public function testFindEagerIndexBy(): void
    {
        $this->checkFixture($this->db(), 'order');

        $orderQuery = new ActiveQuery(Order::class);
        $order = $orderQuery->with('itemsIndexed')->where(['id' => 1])->one();
        $this->assertTrue($order->isRelationPopulated('itemsIndexed'));

        $items = $order->getItemsIndexed();
        $this->assertCount(2, $items);
        $this->assertTrue(isset($items[1]));
        $this->assertTrue(isset($items[2]));

        $order = $orderQuery->with('itemsIndexed')->where(['id' => 2])->one();
        $this->assertTrue($order->isRelationPopulated('itemsIndexed'));

        $items = $order->getItemsIndexed();
        $this->assertCount(3, $items);
        $this->assertTrue(isset($items[3]));
        $this->assertTrue(isset($items[4]));
        $this->assertTrue(isset($items[5]));
    }

    public function testFindLazy(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerQuery = new ActiveQuery(Customer::class);
        $customer = $customerQuery->findOne(2);
        $this->assertFalse($customer->isRelationPopulated('orders'));

        $orders = $customer->getOrders();
        $this->assertTrue($customer->isRelationPopulated('orders'));
        $this->assertCount(2, $orders);
        $this->assertCount(1, $customer->getRelatedRecords());

        $customer->resetRelation('orders');
        $this->assertFalse($customer->isRelationPopulated('orders'));

        $customer = $customerQuery->findOne(2);
        $this->assertFalse($customer->isRelationPopulated('orders'));

        $orders = $customer->getOrdersQuery()->where(['id' => 3])->all();
        $this->assertFalse($customer->isRelationPopulated('orders'));
        $this->assertCount(0, $customer->getRelatedRecords());
        $this->assertCount(1, $orders);
        $this->assertEquals(3, $orders[0]->getId());
    }

    public function testFindLazyVia(): void
    {
        $this->checkFixture($this->db(), 'order');

        $orderQuery = new ActiveQuery(Order::class);
        $order = $orderQuery->findOne(1);

        $this->assertEquals(1, $order->getId());
        $this->assertCount(2, $order->getItems());
        $this->assertEquals(1, $order->getItems()[0]->getId());
        $this->assertEquals(2, $order->getItems()[1]->getId());
    }
}
