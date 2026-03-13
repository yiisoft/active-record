<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use DateTimeImmutable;
use Yiisoft\ActiveRecord\Event\AfterInsert;
use Yiisoft\ActiveRecord\Event\AfterSave;
use Yiisoft\ActiveRecord\Event\AfterUpdate;
use Yiisoft\ActiveRecord\Event\AfterUpsert;
use Yiisoft\ActiveRecord\Event\BeforeCreateQuery;
use Yiisoft\ActiveRecord\Event\BeforeDelete;
use Yiisoft\ActiveRecord\Event\BeforeInsert;
use Yiisoft\ActiveRecord\Event\BeforePopulate;
use Yiisoft\ActiveRecord\Event\BeforeSave;
use Yiisoft\ActiveRecord\Event\BeforeUpdate;
use Yiisoft\ActiveRecord\Event\BeforeUpsert;
use Yiisoft\ActiveRecord\Event\EventDispatcherProvider;
use Yiisoft\ActiveRecord\Event\Handler\DefaultDateTimeOnInsert;
use Yiisoft\ActiveRecord\Event\Handler\DefaultValue;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CategoryEventsModel;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Order;
use Yiisoft\Test\Support\EventDispatcher\SimpleEventDispatcher;

abstract class EventsTraitTest extends TestCase
{
    public function setUp(): void
    {
        EventDispatcherProvider::reset();
    }

    public function testInsertWithEventPrevention(): void
    {
        EventDispatcherProvider::set(
            CategoryEventsModel::class,
            new SimpleEventDispatcher(
                static function (object $event): void {
                    if ($event instanceof BeforeInsert) {
                        $event->preventDefault();
                    }
                },
            ),
        );

        $model = new CategoryEventsModel();
        $model->name = 'Prevented Category';

        $model->insert();

        $this->assertNull($model->id);
        $this->assertSame('Prevented Category', $model->name);
    }

    public function testInsertWithEventPreventionAndCustomReturnValue(): void
    {
        EventDispatcherProvider::set(
            CategoryEventsModel::class,
            new SimpleEventDispatcher(
                static function (object $event): void {
                    if ($event instanceof BeforeInsert) {
                        $event->preventDefault();
                        $event->returnValue(true);
                    }
                },
            ),
        );

        $model = new CategoryEventsModel();
        $model->name = 'Custom Return Category';

        $model->insert();

        $this->assertNull($model->id);
        $this->assertSame('Custom Return Category', $model->name);
    }

    public function testPopulateRecordWithEventPrevention(): void
    {
        EventDispatcherProvider::set(
            CategoryEventsModel::class,
            new SimpleEventDispatcher(
                static function (object $event): void {
                    if ($event instanceof BeforePopulate) {
                        $event->preventDefault();
                    }
                },
            ),
        );

        $model = new CategoryEventsModel();
        $data = ['id' => 1, 'name' => 'Prevented Category'];

        $model->populateRecord($data);

        $this->assertNull($model->id);
        $this->assertNull($model->name);
    }

    public function testQueryWithEventPrevention(): void
    {
        $customQuery = CategoryEventsModel::query()->where(['name' => 'test']);

        EventDispatcherProvider::set(
            CategoryEventsModel::class,
            new SimpleEventDispatcher(
                static function (object $event) use ($customQuery): void {
                    if ($event instanceof BeforeCreateQuery) {
                        $event->preventDefault();
                        $event->returnValue($customQuery);
                    }
                },
            ),
        );

        $query = CategoryEventsModel::query();

        $this->assertSame($customQuery, $query);
    }

    public function testSaveWithEventPrevention(): void
    {
        EventDispatcherProvider::set(
            CategoryEventsModel::class,
            new SimpleEventDispatcher(
                static function (object $event): void {
                    if ($event instanceof BeforeSave) {
                        $event->preventDefault();
                    }
                },
            ),
        );

        $model = new CategoryEventsModel();
        $model->name = 'Prevented Save';

        $model->save();

        $this->assertNull($model->id);
        $this->assertSame('Prevented Save', $model->name);
    }

    public function testSaveWithEventPreventionAndCustomReturnValue(): void
    {
        EventDispatcherProvider::set(
            CategoryEventsModel::class,
            new SimpleEventDispatcher(
                static function (object $event): void {
                    if ($event instanceof BeforeSave) {
                        $event->preventDefault();
                        $event->returnValue(true);
                    }
                },
            ),
        );

        $model = new CategoryEventsModel();
        $model->name = 'Custom Return Save';

        $model->save();

        $this->assertNull($model->id);
        $this->assertSame('Custom Return Save', $model->name);
    }

    public function testDeleteWithEventPrevention(): void
    {
        EventDispatcherProvider::set(
            CategoryEventsModel::class,
            new SimpleEventDispatcher(
                static function (object $event): void {
                    if ($event instanceof BeforeDelete) {
                        $event->preventDefault();
                    }
                },
            ),
        );

        $model = CategoryEventsModel::query()->findByPk(1);

        $this->assertSame(0, $model->delete());
        $this->assertNotNull(CategoryEventsModel::query()->findByPk(1));
    }

    public function testUpdateWithEventPrevention(): void
    {
        EventDispatcherProvider::set(
            CategoryEventsModel::class,
            new SimpleEventDispatcher(
                static function (object $event): void {
                    if ($event instanceof BeforeUpdate) {
                        $event->preventDefault();
                    }
                },
            ),
        );

        $model = CategoryEventsModel::query()->findByPk(1);
        $originalName = $model->name;
        $model->name = 'Prevented Update';

        $result = $model->update();

        $this->assertSame(0, $result);
        $this->assertSame('Prevented Update', $model->name);

        $reloadedModel = CategoryEventsModel::query()->findByPk(1);
        $this->assertSame($originalName, $reloadedModel->name);
    }

    public function testUpdateWithEventPreventionAndCustomReturnValue(): void
    {
        EventDispatcherProvider::set(
            CategoryEventsModel::class,
            new SimpleEventDispatcher(
                static function (object $event): void {
                    if ($event instanceof BeforeUpdate) {
                        $event->preventDefault();
                        $event->returnValue(1);
                    }
                },
            ),
        );

        $model = CategoryEventsModel::query()->findByPk(1);
        $originalName = $model->name;
        $model->name = 'Custom Return Update';

        $result = $model->update();

        $this->assertSame(1, $result);
        $this->assertSame('Custom Return Update', $model->name);

        $reloadedModel = CategoryEventsModel::query()->findByPk(1);
        $this->assertSame($originalName, $reloadedModel->name);
    }

    public function testUpsertWithEventPrevention(): void
    {
        EventDispatcherProvider::set(
            CategoryEventsModel::class,
            new SimpleEventDispatcher(
                static function (object $event): void {
                    if ($event instanceof BeforeUpsert) {
                        $event->preventDefault();
                    }
                },
            ),
        );

        $model = new CategoryEventsModel();
        $model->name = 'Prevented Upsert';

        $model->upsert();

        $this->assertNull($model->id);
        $this->assertSame('Prevented Upsert', $model->name);
    }

    public function testUpsertWithEventPreventionAndCustomReturnValue(): void
    {
        EventDispatcherProvider::set(
            CategoryEventsModel::class,
            new SimpleEventDispatcher(
                static function (object $event): void {
                    if ($event instanceof BeforeUpsert) {
                        $event->preventDefault();
                        $event->returnValue(true);
                    }
                },
            ),
        );

        $model = new CategoryEventsModel();
        $model->name = 'Custom Return Upsert';

        $model->upsert();

        $this->assertNull($model->id);
        $this->assertSame('Custom Return Upsert', $model->name);
    }

    public function testAfterEventsAreDispatched(): void
    {
        $triggeredEvents = [];

        EventDispatcherProvider::set(
            CategoryEventsModel::class,
            new SimpleEventDispatcher(
                static function (object $event) use (&$triggeredEvents): void {
                    $triggeredEvents[] = $event::class;
                },
            ),
        );

        $model = new CategoryEventsModel();
        $model->id = 100;
        $model->name = 'After Insert';
        $model->insert();

        $model->name = 'After Save';
        $model->save();

        $model->name = 'After Update';
        $model->update();

        $model = new CategoryEventsModel();
        $model->id = 101;
        $model->name = 'After Upsert';
        $model->upsert();

        $this->assertContains(AfterInsert::class, $triggeredEvents);
        $this->assertContains(AfterSave::class, $triggeredEvents);
        $this->assertContains(AfterUpdate::class, $triggeredEvents);
        $this->assertContains(AfterUpsert::class, $triggeredEvents);
    }

    public function testEventsKeepModelReference(): void
    {
        $model = new CategoryEventsModel();
        $properties = ['name'];
        $count = 1;
        $data = ['id' => 1];

        $this->assertSame($model, (new AfterInsert($model))->model);
        $this->assertSame($model, (new AfterSave($model))->model);
        $this->assertSame($model, (new AfterUpdate($model, $count))->model);
        $this->assertSame($model, (new AfterUpsert($model))->model);
        $this->assertSame($model, (new BeforePopulate($model, $data))->model);
        $this->assertSame($model, (new BeforeSave($model, $properties))->model);
    }

    public function testAttributeHandlerProviderPropertyNamesArePublic(): void
    {
        $handler = new DefaultValue('value', 'name', 'status');

        $this->assertSame(['name', 'status'], $handler->getPropertyNames());
    }

    public function testDefaultDateTimeOnInsertUsesCustomValue(): void
    {
        $dateTime = new DateTimeImmutable('2024-01-01 12:34:56');
        $handler = new DefaultDateTimeOnInsert($dateTime, 'created_at');
        $eventHandlers = $handler->getEventHandlers();
        $beforeInsert = $eventHandlers[BeforeInsert::class];

        $order = new Order();
        $order->setCustomerId(1);
        $order->setTotal(10.0);
        $properties = null;

        $event = new BeforeInsert($order, $properties);
        $beforeInsert($event);

        $this->assertSame($dateTime, $order->getCreatedAt());
    }
}
