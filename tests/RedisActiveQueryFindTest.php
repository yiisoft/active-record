<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\Redis\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\CustomerQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Dummy;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Item;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Order;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\OrderItem;
use Yiisoft\Db\Exception\InvalidConfigException;

abstract class RedisActiveQueryFindTest extends TestCase
{
    public function testCallFind(): void
    {
        $this->customerData();

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);

        /** find count, sum, average, min, max, scalar */
        $this->assertEquals(3, $customerQuery->count());
        $this->assertEquals(6, $customerQuery->sum('id'));
        $this->assertEquals(2, $customerQuery->average('id'));
        $this->assertEquals(1, $customerQuery->min('id'));
        $this->assertEquals(3, $customerQuery->max('id'));
        $this->assertEquals(2, $customerQuery->where(['in', 'id', [1, 2]])->count());
    }

    public function testFindAll(): void
    {
        $this->customerData();

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $this->assertCount(1, $customerQuery->findAll(3));

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $this->assertCount(1, $customerQuery->findAll(['id' => 1]));

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $this->assertCount(3, $customerQuery->findAll(['id' => [1, 2, 3]]));
    }

    public function testFindScalar(): void
    {
        $this->customerData();

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);

        /** query scalar */
        $customerName = $customerQuery->where(['id' => 2])->withAttribute('name')->scalar();
        $this->assertEquals('user2', $customerName);

        $customerName = $customerQuery->where(['id' => 2])->withAttribute('name')->scalar();
        $this->assertEquals('user2', $customerName);

        $customerName = $customerQuery->where(['status' => 2])->withAttribute('name')->scalar();
        $this->assertEquals('user3', $customerName);

        $customerName = $customerQuery->where(['status' => 2])->withAttribute('noname')->scalar();
        $this->assertNull($customerName);

        $customerId = $customerQuery->where(['status' => 2])->withAttribute('id')->scalar();
        $this->assertEquals(3, $customerId);
    }

    public function testFindExists(): void
    {
        $this->customerData();

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);

        $this->assertTrue($customerQuery->where(['id' => 2])->exists());
        $this->assertTrue($customerQuery->where(['id' => 2])->withAttribute('name')->exists());
        $this->assertFalse($customerQuery->where(['id' => 42])->exists());
        $this->assertFalse($customerQuery->where(['id' => 42])->withAttribute('name')->exists());
    }

    public function testFindColumn(): void
    {
        $this->customerData();

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);

        $this->assertEquals(['user1', 'user2', 'user3'], $customerQuery->withAttribute('name')->column());
        $this->assertEquals(
            ['user3', 'user2', 'user1'],
            $customerQuery->orderBy(['name' => SORT_DESC])->withAttribute('name')->column()
        );
    }

    public function testFindLazyViaTable(): void
    {
        $this->orderData();

        $orderQuery = new ActiveQuery(Order::class, $this->redisConnection);

        $orders = $orderQuery->findOne(2);
        $this->assertCount(0, $orders->books);
        $this->assertEquals(2, $orders->id);

        $orders = $orderQuery->where(['id' => 1])->asArray()->one();
        $this->assertIsArray($orders);
    }

    public function testFindEagerViaTable(): void
    {
        $this->itemData();
        $this->orderData();
        $this->orderItemData();

        $orderQuery = new ActiveQuery(Order::class, $this->redisConnection);
        $orders = $orderQuery->with('books')->orderBy('id')->all();
        $this->assertCount(3, $orders);

        $order = $orders[0];
        $this->assertCount(2, $order->books);
        $this->assertEquals(1, $order->id);
        $this->assertEquals(1, $order->books[0]->id);
        $this->assertEquals(2, $order->books[1]->id);

        $order = $orders[1];
        $this->assertCount(0, $order->books);
        $this->assertEquals(2, $order->id);

        $order = $orders[2];
        $this->assertCount(1, $order->books);
        $this->assertEquals(3, $order->id);
        $this->assertEquals(2, $order->books[0]->id);

        /** {@see https://github.com/yiisoft/yii2/issues/1402} */
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
     * overridden because null values are not part of the asArray result in redis
     */
    public function testFindAsArray(): void
    {
        $this->customerData();

        /** asArray */
        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customer = $customerQuery->where(['id' => 2])->asArray()->one();
        $this->assertEquals([
            'id' => 2,
            'email' => 'user2@example.com',
            'name' => 'user2',
            'address' => 'address2',
            'status' => 1,
        ], $customer);

        /** find all asArray */
        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
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

    public function testFindEmptyWith(): void
    {
        $orderQuery = new ActiveQuery(Order::class, $this->redisConnection);
        $orders = $orderQuery->where(['total' => 100000])->orWhere(['total' => 1])->with('customer')->all();
        $this->assertEquals([], $orders);
    }

    public function testFind(): void
    {
        $this->customerData();

        /** find one */
        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $this->assertInstanceOf(ActiveQueryInterface::class, $customerQuery);

        $customer = $customerQuery->one();
        $this->assertInstanceOf(Customer::class, $customer);

        /** find all */
        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customers = $customerQuery->all();
        $this->assertCount(3, $customers);
        $this->assertInstanceOf(Customer::class, $customers[0]);
        $this->assertInstanceOf(Customer::class, $customers[1]);
        $this->assertInstanceOf(Customer::class, $customers[2]);

        /** find by a single primary key */
        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customer = $customerQuery->findOne(2);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('user2', $customer->name);

        $customer = $customerQuery->findOne(5);
        $this->assertNull($customer);

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customer = $customerQuery->findOne(['id' => [5, 6, 1]]);
        $this->assertInstanceOf(Customer::class, $customer);

        $customer = $customerQuery->where(['id' => [5, 6, 1]])->one();
        $this->assertNotNull($customer);

        /** find by column values */
        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customer = $customerQuery->findOne(['id' => 2, 'name' => 'user2']);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('user2', $customer->name);

        $customer = $customerQuery->findOne(['id' => 2, 'name' => 'user1']);
        $this->assertNull($customer);

        $customer = $customerQuery->findOne(['id' => 5]);
        $this->assertNull($customer);

        $customer = $customerQuery->findOne(['name' => 'user5']);
        $this->assertNull($customer);

        /** find by attributes */
        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customer = $customerQuery->where(['name' => 'user2'])->one();
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals(2, $customer->id);

        /** scope */
        $customerQuery = new CustomerQuery(Customer::class, $this->redisConnection);
        $this->assertCount(2, $customerQuery->active()->all());
        $this->assertEquals(2, $customerQuery->active()->count());
    }

    public function testFindCount(): void
    {
        $this->customerData();

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $this->assertEquals(3, $customerQuery->count());
        $this->assertEquals(1, $customerQuery->where(['id' => 1])->count());
        $this->assertEquals(2, $customerQuery->where(['id' => [1, 2]])->count());
        $this->assertEquals(2, $customerQuery->where(['id' => [1, 2]])->offset(1)->count());
        $this->assertEquals(2, $customerQuery->where(['id' => [1, 2]])->offset(2)->count());

        /** limit should have no effect on count() */
        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $this->assertEquals(3, $customerQuery->limit(1)->count());
        $this->assertEquals(3, $customerQuery->limit(2)->count());
        $this->assertEquals(3, $customerQuery->limit(10)->count());
        $this->assertEquals(3, $customerQuery->offset(2)->limit(2)->count());
    }

    public function testFindLimit(): void
    {
        $this->customerData();

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customers = $customerQuery->all();
        $this->assertCount(3, $customers);

        $customers = $customerQuery->orderBy('id')->limit(1)->all();
        $this->assertCount(1, $customers);
        $this->assertEquals('user1', $customers[0]->name);

        $customers = $customerQuery->orderBy('id')->limit(1)->offset(1)->all();
        $this->assertCount(1, $customers);
        $this->assertEquals('user2', $customers[0]->name);

        $customers = $customerQuery->orderBy('id')->limit(1)->offset(2)->all();
        $this->assertCount(1, $customers);
        $this->assertEquals('user3', $customers[0]->name);

        $customers = $customerQuery->orderBy('id')->limit(2)->offset(1)->all();
        $this->assertCount(2, $customers);
        $this->assertEquals('user2', $customers[0]->name);
        $this->assertEquals('user3', $customers[1]->name);

        $customers = $customerQuery->limit(2)->offset(3)->all();
        $this->assertCount(0, $customers);

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customer = $customerQuery->orderBy('id')->one();
        $this->assertEquals('user1', $customer->name);

        $customer = $customerQuery->orderBy('id')->offset(0)->one();
        $this->assertEquals('user1', $customer->name);

        $customer = $customerQuery->orderBy('id')->offset(1)->one();
        $this->assertEquals('user2', $customer->name);

        $customer = $customerQuery->orderBy('id')->offset(2)->one();
        $this->assertEquals('user3', $customer->name);

        $customer = $customerQuery->offset(3)->one();
        $this->assertNull($customer);
    }

    public function testFindComplexCondition(): void
    {
        $this->customerData();

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);

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
        $this->customerData();

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customer = $customerQuery->findOne(2);
        $customer->name = null;
        $customer->save();

        $result = $customerQuery->where(['name' => null])->all();
        $this->assertCount(1, $result);
        $this->assertEquals(2, reset($result)->primaryKey);
    }

    public function testFindLazy(): void
    {
        $this->customerData();
        $this->orderData();

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customer = $customerQuery->findOne(2);
        $this->assertFalse($customer->isRelationPopulated('orders'));

        $orders = $customer->orders;
        $this->assertTrue($customer->isRelationPopulated('orders'));
        $this->assertCount(2, $orders);
        $this->assertCount(1, $customer->relatedRecords);

        unset($customer['orders']);
        $this->assertFalse($customer->isRelationPopulated('orders'));

        $customer = $customerQuery->findOne(2);
        $this->assertFalse($customer->isRelationPopulated('orders'));

        $orders = $customer->getOrders()->where(['id' => 3])->all();
        $this->assertFalse($customer->isRelationPopulated('orders'));
        $this->assertCount(0, $customer->relatedRecords);
        $this->assertCount(1, $orders);
        $this->assertEquals(3, $orders[0]->id);
    }

    public function testFindEager(): void
    {
        $this->customerData();
        $this->orderData();

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customers = $customerQuery->with('orders')->indexBy('id')->all();
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

        $customer = $customerQuery->where(['id' => 1])->with('orders')->one();
        $this->assertTrue($customer->isRelationPopulated('orders'));
        $this->assertCount(1, $customer->orders);
        $this->assertCount(1, $customer->relatedRecords);

        /** multiple with() calls */
        $orderQuery = new ActiveQuery(Order::class, $this->redisConnection);
        $orders = $orderQuery->with('customer', 'items')->all();
        $this->assertCount(3, $orders);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[0]->isRelationPopulated('items'));

        $orders = $orderQuery->with('customer')->with('items')->all();
        $this->assertCount(3, $orders);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
    }

    public function testFindLazyVia(): void
    {
        $this->itemData();
        $this->orderData();
        $this->orderItemData();

        $orderQuery = new ActiveQuery(Order::class, $this->redisConnection);
        $order = $orderQuery->findOne(1);

        $this->assertEquals(1, $order->id);
        $this->assertCount(2, $order->items);
        $this->assertEquals(1, $order->items[0]->id);
        $this->assertEquals(2, $order->items[1]->id);
    }

    public function testFindEagerViaRelation(): void
    {
        $this->itemData();
        $this->orderData();
        $this->orderItemData();

        $orderQuery = new ActiveQuery(Order::class, $this->redisConnection);
        $orders = $orderQuery->with('items')->orderBy('id')->all();

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
        $this->customerData();
        $this->itemData();
        $this->orderData();
        $this->orderItemData();

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customers = $customerQuery->with('orders', 'orders.items')->indexBy('id')->all();

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

        $customers = $customerQuery->where(['id' => 1])->with('ordersWithItems')->one();
        $this->assertTrue($customers->isRelationPopulated('ordersWithItems'));
        $this->assertCount(1, $customers->ordersWithItems);

        /** @var Order $order */
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
        $this->itemData();
        $this->orderData();
        $this->orderItemData();

        $orderQuery = new ActiveQuery(Order::class, $this->redisConnection);
        $orders = $orderQuery->with('itemsInOrder1')->orderBy('created_at')->all();
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
        $this->itemData();
        $this->orderData();
        $this->orderItemData();

        $orderQuery = new ActiveQuery(Order::class, $this->redisConnection);
        $orders = $orderQuery->with('itemsInOrder2')->orderBy('created_at')->all();
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

    public function testFindEmptyInCondition(): void
    {
        $this->customerData();

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);

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
        $this->itemData();
        $this->orderData();
        $this->orderItemData();

        $orderQuery = new ActiveQuery(Order::class, $this->redisConnection);

        $order = $orderQuery->with('itemsIndexed')->where(['id' => 1])->one();
        $this->assertTrue($order->isRelationPopulated('itemsIndexed'));

        $items = $order->itemsIndexed;
        $this->assertCount(2, $items);
        $this->assertTrue(isset($items[1]));
        $this->assertTrue(isset($items[2]));

        $order = $orderQuery->with('itemsIndexed')->where(['id' => 2])->one();
        $this->assertTrue($order->isRelationPopulated('itemsIndexed'));

        $items = $order->itemsIndexed;
        $this->assertCount(3, $items);
        $this->assertTrue(isset($items[3]));
        $this->assertTrue(isset($items[4]));
        $this->assertTrue(isset($items[5]));
    }

    public function testFindByConditionException(): void
    {
        $dummy = new ActiveQuery(Dummy::class, $this->redisConnection);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('"Yiisoft\ActiveRecord\Tests\Stubs\Redis\Dummy" must have a primary key.');

        $dummy->findOne([1]);
    }

    private function itemData(): void
    {
        $item = new Item($this->redisConnection);
        $item->setAttributes(['name' => 'Agile Web Application Development with Yii1.1 and PHP5', 'category_id' => 1]);
        $item->save();

        $item = new Item($this->redisConnection);
        $item->setAttributes(['name' => 'Yii 1.1 Application Development Cookbook', 'category_id' => 1]);
        $item->save();

        $item = new Item($this->redisConnection);
        $item->setAttributes(['name' => 'Ice Age', 'category_id' => 2]);
        $item->save();

        $item = new Item($this->redisConnection);
        $item->setAttributes(['name' => 'Toy Story', 'category_id' => 2]);
        $item->save();

        $item = new Item($this->redisConnection);
        $item->setAttributes(['name' => 'Cars', 'category_id' => 2]);
        $item->save();
    }

    private function orderData(): void
    {
        $order = new Order($this->redisConnection);
        $order->setAttributes(['customer_id' => 1, 'created_at' => 1325282384, 'total' => 110.0]);
        $order->save();

        $order = new Order($this->redisConnection);
        $order->setAttributes(['customer_id' => 2, 'created_at' => 1325334482, 'total' => 33.0]);
        $order->save();

        $order = new Order($this->redisConnection);
        $order->setAttributes(['customer_id' => 2, 'created_at' => 1325502201, 'total' => 40.0]);
        $order->save();
    }

    private function orderItemData(): void
    {
        $orderItem = new OrderItem($this->redisConnection);
        $orderItem->setAttributes(['order_id' => 1, 'item_id' => 1, 'quantity' => 1, 'subtotal' => 30.0]);
        $orderItem->save();

        $orderItem = new OrderItem($this->redisConnection);
        $orderItem->setAttributes(['order_id' => 1, 'item_id' => 2, 'quantity' => 2, 'subtotal' => 40.0]);
        $orderItem->save();

        $orderItem = new OrderItem($this->redisConnection);
        $orderItem->setAttributes(['order_id' => 2, 'item_id' => 4, 'quantity' => 1, 'subtotal' => 10.0]);
        $orderItem->save();

        $orderItem = new OrderItem($this->redisConnection);
        $orderItem->setAttributes(['order_id' => 2, 'item_id' => 5, 'quantity' => 1, 'subtotal' => 15.0]);
        $orderItem->save();

        $orderItem = new OrderItem($this->redisConnection);
        $orderItem->setAttributes(['order_id' => 2, 'item_id' => 3, 'quantity' => 1, 'subtotal' => 8.0]);
        $orderItem->save();

        $orderItem = new OrderItem($this->redisConnection);
        $orderItem->setAttributes(['order_id' => 3, 'item_id' => 2, 'quantity' => 1, 'subtotal' => 40.0]);
        $orderItem->save();

        $orderItem = new OrderItem($this->redisConnection);
        $orderItem->setAttributes(['order_id' => 3, 'item_id' => 'nostr', 'quantity' => 1, 'subtotal' => 40.0]);
        $orderItem->save();
    }
}
