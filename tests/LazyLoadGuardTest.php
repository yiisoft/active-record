<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use LogicException;
use Yiisoft\ActiveRecord\Event\AfterPopulate;
use Yiisoft\ActiveRecord\Event\EventDispatcherProvider;
use Yiisoft\ActiveRecord\LazyLoadGuard;
use Yiisoft\ActiveRecord\LazyLoadGuardMode;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerLazyLoadGuardModel;
use Yiisoft\Test\Support\EventDispatcher\SimpleEventDispatcher;
use Yiisoft\Test\Support\Log\SimpleLogger;

abstract class LazyLoadGuardTest extends TestCase
{
    public function setUp(): void
    {
        LazyLoadGuard::reset();
        EventDispatcherProvider::reset();
    }

    public function tearDown(): void
    {
        LazyLoadGuard::reset();
        EventDispatcherProvider::reset();
    }

    public function testLazyLoadIsCounted(): void
    {
        foreach (CustomerLazyLoadGuardModel::query()->all() as $customer) {
            $customer->getOrders();
        }

        $this->assertSame([CustomerLazyLoadGuardModel::class . '::orders' => 3], LazyLoadGuard::getCounters());
    }

    public function testEagerLoadedRelationIsNotCounted(): void
    {
        foreach (CustomerLazyLoadGuardModel::query()->with('orders')->all() as $customer) {
            $customer->getOrders();
        }

        $this->assertSame([], LazyLoadGuard::getCounters());
    }

    public function testLoadedRelationIsCountedOnce(): void
    {
        $customer = CustomerLazyLoadGuardModel::query()->findByPk(1);
        $customer->getOrders();
        $customer->getOrders();

        $this->assertSame([CustomerLazyLoadGuardModel::class . '::orders' => 1], LazyLoadGuard::getCounters());
    }

    public function testModeLogWithoutLoggerReturnsRelation(): void
    {
        $customer = CustomerLazyLoadGuardModel::query()->findByPk(1);

        $this->assertCount(1, $customer->getOrders());
    }

    public function testModeLogIgnoresSingleLazyLoad(): void
    {
        $logger = new SimpleLogger();
        LazyLoadGuard::set(LazyLoadGuardMode::Log, $logger);

        $customer = CustomerLazyLoadGuardModel::query()->findByPk(1);
        $customer->getOrders();

        $this->assertSame([], $logger->getMessages());
    }

    public function testModeLogWritesWarningWithContext(): void
    {
        $logger = new SimpleLogger();
        LazyLoadGuard::set(LazyLoadGuardMode::Log, $logger);

        foreach (CustomerLazyLoadGuardModel::query()->all() as $customer) {
            $customer->getOrders();
        }

        $messages = $logger->getMessages();

        $this->assertCount(2, $messages);
        $this->assertSame('warning', $messages[0]['level']);
        $this->assertStringContainsString('orders', $messages[0]['message']);

        $context = $messages[0]['context'];

        $this->assertSame(CustomerLazyLoadGuardModel::class, $context['model']);
        $this->assertSame('orders', $context['relation']);
        $this->assertSame(2, $context['count']);
        $this->assertIsString($context['trace']);
    }

    public function testModeStrictIgnoresSingleLazyLoad(): void
    {
        LazyLoadGuard::set(LazyLoadGuardMode::Strict);

        $customer = CustomerLazyLoadGuardModel::query()->findByPk(1);

        $this->assertCount(1, $customer->getOrders());
    }

    public function testModeStrictThrowsExceptionWithRelationName(): void
    {
        LazyLoadGuard::set(LazyLoadGuardMode::Strict);

        $customers = CustomerLazyLoadGuardModel::query()->all();
        $customers[0]->getOrders();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Relation "' . CustomerLazyLoadGuardModel::class . '::orders" is lazy loaded.');

        $customers[1]->getOrders();
    }

    public function testModeStrictCountsLazyLoadBeforeThrowing(): void
    {
        LazyLoadGuard::set(LazyLoadGuardMode::Strict);

        $customers = CustomerLazyLoadGuardModel::query()->all();
        $customers[0]->getOrders();

        try {
            $customers[1]->getOrders();
        } catch (LogicException) {
        }

        $this->assertSame([CustomerLazyLoadGuardModel::class . '::orders' => 2], LazyLoadGuard::getCounters());
    }

    public function testWorksWithEventsTrait(): void
    {
        $events = [];

        EventDispatcherProvider::set(
            CustomerLazyLoadGuardModel::class,
            new SimpleEventDispatcher(
                static function (object $event) use (&$events): void {
                    $events[] = $event::class;
                },
            ),
        );

        $customer = CustomerLazyLoadGuardModel::query()->findByPk(1);
        $customer->getOrders();

        $this->assertContains(AfterPopulate::class, $events);
        $this->assertSame([CustomerLazyLoadGuardModel::class . '::orders' => 1], LazyLoadGuard::getCounters());
    }

    public function testReset(): void
    {
        LazyLoadGuard::set(LazyLoadGuardMode::Strict, new SimpleLogger());
        LazyLoadGuard::reset();

        $customer = CustomerLazyLoadGuardModel::query()->findByPk(1);
        $customer->getOrders();

        $this->assertSame([CustomerLazyLoadGuardModel::class . '::orders' => 1], LazyLoadGuard::getCounters());

        LazyLoadGuard::reset();

        $this->assertSame([], LazyLoadGuard::getCounters());
    }
}
