<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\ActiveDataProvider;
use Yiisoft\Db\Query\Query;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Item;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Order;

abstract class ActiveDataProviderFactoryTest extends TestCase
{
    public function testActiveQuery(): void
    {
        $this->loadFixture($this->arFactory->getConnection());

        $query = $this->arFactory->createQueryTo(Order::class);

        $provider = new ActiveDataProvider($query->orderBy('id'));

        $orders = $provider->getModels();
        $this->assertCount(3, $orders);
        $this->assertInstanceOf(Order::class, $orders[0]);
        $this->assertInstanceOf(Order::class, $orders[1]);
        $this->assertInstanceOf(Order::class, $orders[2]);
        $this->assertEquals([1, 2, 3], $provider->getKeys());
    }

    public function testActiveRelation(): void
    {
        $customer = $this->arFactory->createAR(Customer::class)->findOne(2);

        $provider = new ActiveDataProvider($customer->getOrders());

        $orders = $provider->getModels();
        $this->assertCount(2, $orders);
        $this->assertInstanceOf(Order::class, $orders[0]);
        $this->assertInstanceOf(Order::class, $orders[1]);
        $this->assertEquals([2, 3], $provider->getKeys());
    }

    public function testActiveRelationVia(): void
    {
        $order = $this->arFactory->createAR(Order::class)->findOne(2);

        $provider = new ActiveDataProvider($order->getItems());

        $items = $provider->getModels();
        $this->assertCount(3, $items);
        $this->assertInstanceOf(Item::class, $items[0]);
        $this->assertInstanceOf(Item::class, $items[1]);
        $this->assertInstanceOf(Item::class, $items[2]);
        $this->assertEquals([3, 4, 5], $provider->getKeys());
    }

    public function testActiveRelationViaTable(): void
    {
        $order = $this->arFactory->createAR(Order::class)->findOne(1);

        $provider = new ActiveDataProvider($order->getBooks());

        $items = $provider->getModels();
        $this->assertCount(2, $items);
        $this->assertInstanceOf(Item::class, $items[0]);
        $this->assertInstanceOf(Item::class, $items[1]);
    }

    public function testQuery(): void
    {
        $query = new Query($this->arFactory->getConnection());

        $provider = new ActiveDataProvider($query->from('order')->orderBy('id'));

        $orders = $provider->getModels();
        $this->assertCount(3, $orders);
        $this->assertIsArray($orders[0]);
        $this->assertEquals([0, 1, 2], $provider->getKeys());
    }
}
