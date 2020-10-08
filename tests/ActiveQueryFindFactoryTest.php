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

abstract class ActiveQueryFindFactoryTest extends TestCase
{
    public function testFindAll(): void
    {
        $this->loadFixture($this->arFactory->getConnection());

        $customerQuery = $this->arFactory->createQueryTo(Customer::class);
        $this->assertCount(1, $customerQuery->findAll(3));

        $customerQuery = $this->arFactory->createQueryTo(Customer::class);
        $this->assertCount(1, $customerQuery->findAll(['id' => 1]));

        $customerQuery = $this->arFactory->createQueryTo(Customer::class);
        $this->assertCount(3, $customerQuery->findAll(['id' => [1, 2, 3]]));
    }

    public function testFindScalar(): void
    {
        $customerQuery = $this->arFactory->createQueryTo(Customer::class);

        /** query scalar */
        $customerName = $customerQuery->where(['[[id]]' => 2])->select('[[name]]')->scalar();

        $this->assertEquals('user2', $customerName);
    }

    public function testFindExists(): void
    {
        $customerQuery = $this->arFactory->createQueryTo(Customer::class);

        $this->assertTrue($customerQuery->where(['[[id]]' => 2])->exists());
        $this->assertTrue($customerQuery->where(['[[id]]' => 2])->select('[[name]]')->exists());

        $this->assertFalse($customerQuery->where(['[[id]]' => 42])->exists());
        $this->assertFalse($customerQuery->where(['[[id]]' => 42])->select('[[name]]')->exists());
    }

    public function testFindColumn(): void
    {
        $customerQuery = $this->arFactory->createQueryTo(Customer::class);

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
        $customerQuery = $this->arFactory->createQueryTo(Customer::class);

        /** find one */
        $customers = $customerQuery->findBySql('SELECT * FROM {{customer}} ORDER BY [[id]] DESC')->one();
        $this->assertInstanceOf(Customer::class, $customers);
        $this->assertEquals('user3', $customers->getAttribute('name'));

        /** find all */
        $customers = $customerQuery->findBySql('SELECT * FROM {{customer}}')->all();
        $this->assertCount(3, $customers);

        /** find with parameter binding */
        $customers = $customerQuery->findBySql('SELECT * FROM {{customer}} WHERE [[id]]=:id', [':id' => 2])->one();
        $this->assertInstanceOf(Customer::class, $customers);
        $this->assertEquals('user2', $customers->getAttribute('name'));
    }

    public function testFindLazyViaTable(): void
    {
        $orderQuery = $this->arFactory->createQueryTo(Order::class);

        $orders = $orderQuery->findOne(2);
        $this->assertCount(0, $orders->books);
        $this->assertEquals(2, $orders->getAttribute('id'));

        $orders = $orderQuery->where(['id' => 1])->asArray()->one();
        $this->assertIsArray($orders);
    }

    public function testFindEagerViaTable(): void
    {
        $orderQuery = $this->arFactory->createQueryTo(Order::class);
        $orders = $orderQuery->with('books')->orderBy('id')->all();
        $this->assertCount(3, $orders);

        $order = $orders[0];
        $this->assertCount(2, $order->books);
        $this->assertEquals(1, $order->getAttribute('id'));
        $this->assertEquals(1, $order->books[0]->getAttribute('id'));
        $this->assertEquals(2, $order->books[1]->getAttribute('id'));

        $order = $orders[1];
        $this->assertCount(0, $order->books);
        $this->assertEquals(2, $order->getAttribute('id'));

        $order = $orders[2];
        $this->assertCount(1, $order->books);
        $this->assertEquals(3, $order->getAttribute('id'));
        $this->assertEquals(2, $order->books[0]->getAttribute('id'));

        /** https://github.com/yiisoft/yii2/issues/1402 */
        $orderQuery = $this->arFactory->createQueryTo(Order::class);
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
        $orderItemQuery = $this->arFactory->createQueryTo(OrderItem::class);

        /** @var $orderItems OrderItem */
        $orderItems = $orderItemQuery->findOne([1, 1]);

        $orderItemNoJoin = $orderItems->orderItemCompositeNoJoin;
        $this->assertInstanceOf(OrderItem::class, $orderItemNoJoin);

        $orderItemWithJoin = $orderItems->orderItemCompositeWithJoin;
        $this->assertInstanceOf(OrderItem::class, $orderItemWithJoin);
    }

    public function testFindSimpleRelationWithJoin(): void
    {
        $orderQuery = $this->arFactory->createQueryTo(Order::class);

        $orders = $orderQuery->findOne(1);
        $customerNoJoin = $orders->customer;
        $this->assertInstanceOf(Customer::class, $customerNoJoin);

        $customerWithJoin = $orders->customerJoinedWithProfile;
        $this->assertInstanceOf(Customer::class, $customerWithJoin);

        $customerWithJoinIndexOrdered = $orders->customerJoinedWithProfileIndexOrdered;
        $this->assertArrayHasKey('user1', $customerWithJoinIndexOrdered);
        $this->assertInstanceOf(Customer::class, $customerWithJoinIndexOrdered['user1']);
        $this->assertIsArray($customerWithJoinIndexOrdered);
    }

    public function testFindOneByColumnName(): void
    {
        $customer = $this->arFactory->createQueryTo(Customer::class);
        $customerQuery = $this->arFactory->createQueryTo(Customer::class, CustomerQuery::class);

        $arClass = $customer->findOne(['id' => 1]);
        $this->assertEquals(1, $arClass->id);

        $customerQuery->joinWithProfile = true;

        $arClass = $customer->findOne(['customer.id' => 1]);
        $this->assertEquals(1, $arClass->id);

        $customerQuery->joinWithProfile = false;
    }

    public function testFind(): void
    {
        $customerQuery = $this->arFactory->createQueryTo(Customer::class);
        $this->assertInstanceOf(ActiveQueryInterface::class, $customerQuery);

        /** find one */
        $customer = $customerQuery->one();
        $this->assertInstanceOf(Customer::class, $customer);

        /** find all */
        $customerQuery = $this->arFactory->createQueryTo(Customer::class);
        $customers = $customerQuery->all();
        $this->assertCount(3, $customers);
        $this->assertInstanceOf(Customer::class, $customers[0]);
        $this->assertInstanceOf(Customer::class, $customers[1]);
        $this->assertInstanceOf(Customer::class, $customers[2]);

        /** find by a single primary key */
        $customerQuery = $this->arFactory->createQueryTo(Customer::class);
        $customer = $customerQuery->findOne(2);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('user2', $customer->name);

        $customer = $customerQuery->findOne(5);
        $this->assertNull($customer);

        $customerQuery = $this->arFactory->createQueryTo(Customer::class);
        $customer = $customerQuery->findOne(['id' => [5, 6, 1]]);
        $this->assertInstanceOf(Customer::class, $customer);

        $customer = $customerQuery->where(['id' => [5, 6, 1]])->one();
        $this->assertNotNull($customer);

        /** find by column values */
        $customerQuery = $this->arFactory->createQueryTo(Customer::class);
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
        $customerQuery = $this->arFactory->createQueryTo(Customer::class);
        $customer = $customerQuery->where(['name' => 'user2'])->one();
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals(2, $customer->id);

        /** scope */
        $customerQuery = $this->arFactory->createQueryTo(Customer::class, CustomerQuery::class);
        $this->assertCount(2, $customerQuery->active()->all());
        $this->assertEquals(2, $customerQuery->active()->count());
    }

    public function testFindAsArray(): void
    {
        $this->loadFixture($this->arFactory->getConnection());

        /** asArray */
        $customerQuery = $this->arFactory->createQueryTo(Customer::class);
        $customer = $customerQuery->where(['id' => 2])->asArray()->one();
        $this->assertEquals([
            'id' => 2,
            'email' => 'user2@example.com',
            'name' => 'user2',
            'address' => 'address2',
            'status' => 1,
            'profile_id' => null,
        ], $customer);

        /** find all asArray */
        $customerQuery = $this->arFactory->createQueryTo(Customer::class);
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
        $customerQuery = $this->arFactory->createQueryTo(Customer::class);

        $customers = $customerQuery->indexBy('name')->orderBy('id')->all();

        $this->assertCount(3, $customers);
        $this->assertInstanceOf(Customer::class, $customers['user1']);
        $this->assertInstanceOf(Customer::class, $customers['user2']);
        $this->assertInstanceOf(Customer::class, $customers['user3']);

        /** indexBy callable */
        $customer = $this->arFactory->createQueryTo(Customer::class);

        $customers = $customer->indexBy(function (Customer $customer) {
            return $customer->id . '-' . $customer->name;
        })->orderBy('id')->all();

        $this->assertCount(3, $customers);
        $this->assertInstanceOf(Customer::class, $customers['1-user1']);
        $this->assertInstanceOf(Customer::class, $customers['2-user2']);
        $this->assertInstanceOf(Customer::class, $customers['3-user3']);
    }

    public function testFindIndexByAsArray(): void
    {
        $customerQuery = $this->arFactory->createQueryTo(Customer::class);
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
        $customerQuery = $this->arFactory->createQueryTo(Customer::class);
        $customers = $customerQuery->indexBy(function ($customer) {
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

    public function testFindCount(): void
    {
        $customerQuery = $this->arFactory->createQueryTo(Customer::class);
        $this->assertEquals(3, $customerQuery->count());
        $this->assertEquals(1, $customerQuery->where(['id' => 1])->count());
        $this->assertEquals(2, $customerQuery->where(['id' => [1, 2]])->count());
        $this->assertEquals(2, $customerQuery->where(['id' => [1, 2]])->offset(1)->count());
        $this->assertEquals(2, $customerQuery->where(['id' => [1, 2]])->offset(2)->count());

        $customerQuery = $this->arFactory->createQueryTo(Customer::class);
        $this->assertEquals(3, $customerQuery->limit(1)->count());
        $this->assertEquals(3, $customerQuery->limit(2)->count());
        $this->assertEquals(3, $customerQuery->limit(10)->count());
        $this->assertEquals(3, $customerQuery->offset(2)->limit(2)->count());
    }

    public function testFindLimit(): void
    {
        /** one */
        $customerQuery = $this->arFactory->createQueryTo(Customer::class);
        $customer = $customerQuery->orderBy('id')->one();
        $this->assertEquals('user1', $customer->name);

        /** all */
        $customerQuery = $this->arFactory->createQueryTo(Customer::class);
        $customers = $customerQuery->all();
        $this->assertCount(3, $customers);

        /** limit */
        $customerQuery = $this->arFactory->createQueryTo(Customer::class);
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

        /** offset */
        $customerQuery = $this->arFactory->createQueryTo(Customer::class);
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
        $customerQuery = $this->arFactory->createQueryTo(Customer::class);

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
        $customerQuery = $this->arFactory->createQueryTo(Customer::class);

        $customer = $customerQuery->findOne(2);
        $customer->name = null;
        $customer->save();

        $result = $customerQuery->where(['name' => null])->all();
        $this->assertCount(1, $result);
        $this->assertEquals(2, reset($result)->primaryKey);
    }

    public function testFindEager(): void
    {
        $customerQuery = $this->arFactory->createQueryTo(Customer::class);
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
        $orderQuery = $this->arFactory->createQueryTo(Order::class);
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
        $orderQuery = $this->arFactory->createQueryTo(Order::class);
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
        $customerQuery = $this->arFactory->createQueryTo(Customer::class);
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
        $orderQuery = $this->arFactory->createQueryTo(Order::class);
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
        /** different order in via table. */
        $orderQuery = $this->arFactory->createQueryTo(Order::class);
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
        $customerQuery = $this->arFactory->createQueryTo(Customer::class);
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
        $orderQuery = $this->arFactory->createQueryTo(Order::class);
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

    public function testFindLazy(): void
    {
        $customerQuery = $this->arFactory->createQueryTo(Customer::class);
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

    public function testFindLazyVia(): void
    {
        $orderQuery = $this->arFactory->createQueryTo(Order::class);
        $order = $orderQuery->findOne(1);

        $this->assertEquals(1, $order->id);
        $this->assertCount(2, $order->items);
        $this->assertEquals(1, $order->items[0]->id);
        $this->assertEquals(2, $order->items[1]->id);
    }
}
