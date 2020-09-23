<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\ActiveDataProvider;
use Yiisoft\Db\Query\Query;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Item;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Order;

abstract class ActiveDataProviderTest extends TestCase
{
    public function testActiveQuery(): void
    {
        $this->loadFixture($this->db);

        $order = new Order($this->db);

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
        $customer = new Customer($this->db);

        $provider = new ActiveDataProvider($customer->findOne(2)->getOrders());

        $orders = $provider->getModels();
        $this->assertCount(2, $orders);
        $this->assertInstanceOf(Order::class, $orders[0]);
        $this->assertInstanceOf(Order::class, $orders[1]);
        $this->assertEquals([2, 3], $provider->getKeys());
    }

    public function testActiveRelationVia(): void
    {
        $order = new Order($this->db);

        $provider = new ActiveDataProvider($order->findOne(2)->getItems());

        $items = $provider->getModels();
        $this->assertCount(3, $items);
        $this->assertInstanceOf(Item::class, $items[0]);
        $this->assertInstanceOf(Item::class, $items[1]);
        $this->assertInstanceOf(Item::class, $items[2]);
        $this->assertEquals([3, 4, 5], $provider->getKeys());
    }

    public function testActiveRelationViaTable(): void
    {
        $order = new Order($this->db);

        $provider = new ActiveDataProvider($order->findOne(1)->getBooks());

        $items = $provider->getModels();
        $this->assertCount(2, $items);
        $this->assertInstanceOf(Item::class, $items[0]);
        $this->assertInstanceOf(Item::class, $items[1]);
    }

    public function testQuery(): void
    {
        $query = new Query($this->db);

        $provider = new ActiveDataProvider(
            $query->from('order')->orderBy('id')
        );

        $orders = $provider->getModels();
        $this->assertCount(3, $orders);
        $this->assertIsArray($orders[0]);
        $this->assertEquals([0, 1, 2], $provider->getKeys());
    }
}
