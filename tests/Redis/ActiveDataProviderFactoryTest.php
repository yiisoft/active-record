<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Redis;

use Yiisoft\ActiveRecord\ActiveDataProvider;
use Yiisoft\ActiveRecord\Tests\TestCase;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Item;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Order;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\OrderItem;

/**
 * @group redis
 */
final class ActiveDataProviderFactoryTest extends TestCase
{
    protected string $driverName = 'redis';

    public function setUp(): void
    {
        parent::setUp();

        $this->redisConnection->open();
        $this->redisConnection->flushdb();

        $this->arFactory->withConnection($this->redisConnection);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->redisConnection->close();

        unset($this->arFactory, $this->redisConnection);
    }

    public function testActiveQuery(): void
    {
        $order = $this->arFactory->createAR(Order::class);

        $this->orderData();

        $provider = new ActiveDataProvider($order->find()->orderBy('id'));

        $orders = $provider->getModels();
        $this->assertCount(3, $orders);
        $this->assertInstanceOf(Order::class, $orders[0]);
        $this->assertInstanceOf(Order::class, $orders[1]);
        $this->assertInstanceOf(Order::class, $orders[2]);
        $this->assertEquals([1, 2, 3], $provider->getKeys());
    }

    public function testActiveRelation(): void
    {
        $customer = $this->arFactory->createAR(Customer::class);

        $this->customerData();
        $this->orderData();

        $provider = new ActiveDataProvider($customer->findOne(2)->getOrders());

        $orders = $provider->getModels();
        $this->assertCount(2, $orders);
        $this->assertInstanceOf(Order::class, $orders[0]);
        $this->assertInstanceOf(Order::class, $orders[1]);
        $this->assertEquals([2, 3], $provider->getKeys());
    }

    public function testActiveRelationVia(): void
    {
        $order = $this->arFactory->createAR(Order::class);

        $this->customerData();
        $this->itemData();
        $this->orderData();
        $this->orderItemData();

        $provider = new ActiveDataProvider($order->findOne(2)->getItems()->orderBy('id'));

        $items = $provider->getModels();
        $this->assertCount(3, $items);
        $this->assertInstanceOf(Item::class, $items[0]);
        $this->assertInstanceOf(Item::class, $items[1]);
        $this->assertInstanceOf(Item::class, $items[2]);
        $this->assertEquals(['3', '4', '5'], $provider->getKeys());
    }

    public function testActiveRelationViaTable(): void
    {
        $order = $this->arFactory->createAR(Order::class);

        $this->customerData();
        $this->itemData();
        $this->orderData();
        $this->orderItemData();

        $provider = new ActiveDataProvider($order->findOne(1)->getBooks());

        $items = $provider->getModels();
        $this->assertCount(2, $items);
        $this->assertInstanceOf(Item::class, $items[0]);
        $this->assertInstanceOf(Item::class, $items[1]);
    }

    private function itemData(): void
    {
        $item = $this->arFactory->createAR(Item::class);
        $item->setAttributes(['name' => 'Agile Web Application Development with Yii1.1 and PHP5', 'category_id' => 1]);
        $item->save();

        $item = $this->arFactory->createAR(Item::class);
        $item->setAttributes(['name' => 'Yii 1.1 Application Development Cookbook', 'category_id' => 1]);
        $item->save();

        $item = $this->arFactory->createAR(Item::class);
        $item->setAttributes(['name' => 'Ice Age', 'category_id' => 2]);
        $item->save();

        $item = $this->arFactory->createAR(Item::class);
        $item->setAttributes(['name' => 'Toy Story', 'category_id' => 2]);
        $item->save();

        $item = $this->arFactory->createAR(Item::class);
        $item->setAttributes(['name' => 'Cars', 'category_id' => 2]);
        $item->save();
    }

    private function orderData(): void
    {
        $order = $this->arFactory->createAR(Order::class);
        $order->setAttributes(['customer_id' => 1, 'created_at' => 1325282384, 'total' => 110.0]);
        $order->save();

        $order = $this->arFactory->createAR(Order::class);
        $order->setAttributes(['customer_id' => 2, 'created_at' => 1325334482, 'total' => 33.0]);
        $order->save();

        $order = $this->arFactory->createAR(Order::class);
        $order->setAttributes(['customer_id' => 2, 'created_at' => 1325502201, 'total' => 40.0]);
        $order->save();
    }

    private function orderItemData(): void
    {
        $orderItem = $this->arFactory->createAR(OrderItem::class);
        $orderItem->setAttributes(['order_id' => 1, 'item_id' => 1, 'quantity' => 1, 'subtotal' => 30.0]);
        $orderItem->save();

        $orderItem = $this->arFactory->createAR(OrderItem::class);
        $orderItem->setAttributes(['order_id' => 1, 'item_id' => 2, 'quantity' => 2, 'subtotal' => 40.0]);
        $orderItem->save();

        $orderItem = $this->arFactory->createAR(OrderItem::class);
        $orderItem->setAttributes(['order_id' => 2, 'item_id' => 4, 'quantity' => 1, 'subtotal' => 10.0]);
        $orderItem->save();

        $orderItem = $this->arFactory->createAR(OrderItem::class);
        $orderItem->setAttributes(['order_id' => 2, 'item_id' => 5, 'quantity' => 1, 'subtotal' => 15.0]);
        $orderItem->save();

        $orderItem = $this->arFactory->createAR(OrderItem::class);
        $orderItem->setAttributes(['order_id' => 2, 'item_id' => 3, 'quantity' => 1, 'subtotal' => 8.0]);
        $orderItem->save();

        $orderItem = $this->arFactory->createAR(OrderItem::class);
        $orderItem->setAttributes(['order_id' => 3, 'item_id' => 2, 'quantity' => 1, 'subtotal' => 40.0]);
        $orderItem->save();

        $orderItem = $this->arFactory->createAR(OrderItem::class);
        $orderItem->setAttributes(['order_id' => 3, 'item_id' => 'nostr', 'quantity' => 1, 'subtotal' => 40.0]);
        $orderItem->save();
    }
}
