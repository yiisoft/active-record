<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\ActiveDataProvider;
use Yiisoft\Db\Exception\InvalidCallException;
use Yiisoft\Db\Query\Query;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Item;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Order;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\UnqueryableQueryMock;

abstract class ActiveDataProviderTest extends TestCase
{
    public function testActiveQuery()
    {
        $db = Order::getConnection();

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

    public function testActiveRelation()
    {
        $db = Customer::getConnection();

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

    public function testActiveRelationVia()
    {
        $db = Customer::getConnection();

        $order = Order::findOne(2);

        $provider = new ActiveDataProvider(
            $db,
            $order->getItems()
        );

        $items = $provider->getModels();
        $this->assertCount(3, $items);
        $this->assertInstanceOf(Item::class, $items[0]);
        $this->assertInstanceOf(item::class, $items[1]);
        $this->assertInstanceOf(Item::class, $items[2]);
        $this->assertEquals([3, 4, 5], $provider->getKeys());
    }

    public function testActiveRelationViaTable()
    {
        $db = Order::getConnection();

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

    public function testQuery()
    {
        $db = Order::getConnection();

        $query = new Query($db);

        $provider = new ActiveDataProvider(
            $db,
            $query->from('order')->orderBy('id')
        );

        $orders = $provider->getModels();
        $this->assertCount(3, $orders);
        $this->assertIsArray($orders[0]);
        $this->assertEquals([0, 1, 2], $provider->getKeys());
    }
}
