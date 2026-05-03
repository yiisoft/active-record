<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Yiisoft\ActiveRecord\ActiveRecordFactory;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Order;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\OrderWithFactory;
use Yiisoft\Factory\Factory;
use Yiisoft\ActiveRecord\Tests\Support\MyService;
use Yiisoft\Factory\NotFoundException;
use Yiisoft\Factory\StrictFactory;

abstract class ActiveRecordFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        ActiveRecordFactory::clear();
        parent::tearDown();
    }

    public function testSet(): void
    {
        $this->assertFalse(ActiveRecordFactory::has());

        ActiveRecordFactory::set(new Factory());

        $this->assertTrue(ActiveRecordFactory::has());
    }

    public function testSetWithClassName(): void
    {
        $className = Order::class;

        $this->assertFalse(ActiveRecordFactory::has($className));

        ActiveRecordFactory::set(new Factory(), $className);

        $this->assertTrue(ActiveRecordFactory::has($className));
    }

    public function testHas(): void
    {
        $this->assertFalse(ActiveRecordFactory::has());

        ActiveRecordFactory::set(new Factory());

        $this->assertTrue(ActiveRecordFactory::has());

        $className = Order::class;

        $this->assertFalse(ActiveRecordFactory::has($className));

        ActiveRecordFactory::set(new Factory(), $className);

        $this->assertTrue(ActiveRecordFactory::has($className));
    }


    public function testClear(): void
    {
        $className = Order::class;

        ActiveRecordFactory::set(new Factory());
        ActiveRecordFactory::set(new Factory(), $className);

        ActiveRecordFactory::clear();

        $this->assertFalse(ActiveRecordFactory::has());
        $this->assertFalse(ActiveRecordFactory::has($className));
    }


    public function testCreate(): void
    {
        $className = OrderWithFactory::class;

        $factory = new Factory(null, [
            MyService::class => [
                '__construct()' => ['custom'],
            ],
        ]);

        ActiveRecordFactory::set($factory);

        $order = ActiveRecordFactory::create($className);
        $this->assertInstanceOf($className, $order);
        $this->assertSame('custom', $order->service->name);
    }

    public function testCreateWithClassNameThrowsExceptionWhenNotFound(): void
    {
        $className = Order::class;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Factory for class '$className' not found");

        ActiveRecordFactory::create(Order::class);
    }

    public function testSetWithStrictFactory(): void
    {
        $factory = new StrictFactory([]);
        ActiveRecordFactory::set($factory);

        $this->assertTrue(ActiveRecordFactory::has());
    }

    public function testCreateWithStrictFactory(): void
    {
        $className = OrderWithFactory::class;

        $factory = new StrictFactory([
            $className => [],
            MyService::class => [
                '__construct()' => ['strict'],
            ],
        ]);
        ActiveRecordFactory::set($factory);

        $order = ActiveRecordFactory::create($className);
        $this->assertInstanceOf($className, $order);
        $this->assertSame('strict', $order->service->name);
    }

    public function testCreateWithStrictFactoryThrowsExceptionWhenNotFound(): void
    {
        $className = OrderWithFactory::class;
        $factory = new StrictFactory([]);
        ActiveRecordFactory::set($factory);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("No definition or class found or resolvable for $className.");

        ActiveRecordFactory::create($className);
    }
}
