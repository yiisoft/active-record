<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Redis;

use Yiisoft\ActiveRecord\ActiveDataProvider;
use Yiisoft\ActiveRecord\BaseActiveRecord;
use Yiisoft\ActiveRecord\Tests\TestCase;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Item;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Order;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\OrderItem;

/**
 * @group redis
 */
final class ActiveDataProviderTest extends TestCase
{
    protected ?string $driverName = 'redis';

    public function setUp(): void
    {
        parent::setUp();

        BaseActiveRecord::connectionId($this->driverName);

        $this->redisConnection->open();
        $this->redisConnection->flushdb();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->redisConnection->close();

        unset($this->redisConnection);
    }

    public function testActiveQuery(): void
    {
        $db = Order::getConnection();

        $this->orderData();

        $provider = new ActiveDataProvider(
            $db,
            Order::find()->orderBy('id')
        );

        $orders = $provider->getModels();
        $this->assertCount(3, $orders);
        $this->assertInstanceOf(Order::class, $orders[0]);
        $this->assertInstanceOf(Order::class, $orders[1]);
        $this->assertInstanceOf(Order::class, $orders[2]);
        $this->assertEquals([1, 2, 3], $provider->getKeys());
    }

    public function testActiveRelation(): void
    {
        $db = Customer::getConnection();

        $this->customerData();
        $this->orderData();

        $customer = Customer::findOne(2);

        $provider = new ActiveDataProvider(
            $db,
            $customer->getOrders()
        );

        $orders = $provider->getModels();
        $this->assertCount(2, $orders);
        $this->assertInstanceOf(Order::class, $orders[0]);
        $this->assertInstanceOf(Order::class, $orders[1]);
        $this->assertEquals([2, 3], $provider->getKeys());
    }

    public function testActiveRelationVia(): void
    {
        $db = Customer::getConnection();

        $this->customerData();
        $this->itemData();
        $this->orderData();
        $this->orderItemData();

        $order = Order::findOne(2);

        $provider = new ActiveDataProvider(
            $db,
            $order->getItems()->orderBy('id')
        );

        $items = $provider->getModels();
        $this->assertCount(3, $items);
        $this->assertInstanceOf(Item::class, $items[0]);
        $this->assertInstanceOf(Item::class, $items[1]);
        $this->assertInstanceOf(Item::class, $items[2]);
        $this->assertEquals(['3', '4', '5'], $provider->getKeys());
    }

    public function testActiveRelationViaTable(): void
    {
        $db = Order::getConnection();

        $this->customerData();
        $this->itemData();
        $this->orderData();
        $this->orderItemData();

        $order = Order::findOne(1);

        $provider = new ActiveDataProvider(
            $db,
            $order->getBooks()
        );

        $items = $provider->getModels();
        $this->assertCount(2, $items);
        $this->assertInstanceOf(Item::class, $items[0]);
        $this->assertInstanceOf(Item::class, $items[1]);
    }

    private function itemData(): void
    {
        $item = new Item();
        $item->setAttributes(['name' => 'Agile Web Application Development with Yii1.1 and PHP5', 'category_id' => 1]);
        $item->save();

        $item = new Item();
        $item->setAttributes(['name' => 'Yii 1.1 Application Development Cookbook', 'category_id' => 1]);
        $item->save();

        $item = new Item();
        $item->setAttributes(['name' => 'Ice Age', 'category_id' => 2]);
        $item->save();

        $item = new Item();
        $item->setAttributes(['name' => 'Toy Story', 'category_id' => 2]);
        $item->save();

        $item = new Item();
        $item->setAttributes(['name' => 'Cars', 'category_id' => 2]);
        $item->save();
    }

    private function orderData(): void
    {
        $order = new Order();
        $order->setAttributes(['customer_id' => 1, 'created_at' => 1325282384, 'total' => 110.0]);
        $order->save();

        $order = new Order();
        $order->setAttributes(['customer_id' => 2, 'created_at' => 1325334482, 'total' => 33.0]);
        $order->save();

        $order = new Order();
        $order->setAttributes(['customer_id' => 2, 'created_at' => 1325502201, 'total' => 40.0]);
        $order->save();
    }

    private function orderItemData(): void
    {
        $orderItem = new OrderItem();
        $orderItem->setAttributes(['order_id' => 1, 'item_id' => 1, 'quantity' => 1, 'subtotal' => 30.0]);
        $orderItem->save();

        $orderItem = new OrderItem();
        $orderItem->setAttributes(['order_id' => 1, 'item_id' => 2, 'quantity' => 2, 'subtotal' => 40.0]);
        $orderItem->save();

        $orderItem = new OrderItem();
        $orderItem->setAttributes(['order_id' => 2, 'item_id' => 4, 'quantity' => 1, 'subtotal' => 10.0]);
        $orderItem->save();

        $orderItem = new OrderItem();
        $orderItem->setAttributes(['order_id' => 2, 'item_id' => 5, 'quantity' => 1, 'subtotal' => 15.0]);
        $orderItem->save();

        $orderItem = new OrderItem();
        $orderItem->setAttributes(['order_id' => 2, 'item_id' => 3, 'quantity' => 1, 'subtotal' => 8.0]);
        $orderItem->save();

        $orderItem = new OrderItem();
        $orderItem->setAttributes(['order_id' => 3, 'item_id' => 2, 'quantity' => 1, 'subtotal' => 40.0]);
        $orderItem->save();

        $orderItem = new OrderItem();
        $orderItem->setAttributes(['order_id' => 3, 'item_id' => 'nostr', 'quantity' => 1, 'subtotal' => 40.0]);
        $orderItem->save();
    }
}
