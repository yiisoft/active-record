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
use Yiisoft\ActiveRecord\Event\Handler\DefaultValueOnInsert;
use Yiisoft\ActiveRecord\Event\Handler\SetDateTimeOnUpdate;
use Yiisoft\ActiveRecord\Event\Handler\SetValueOnUpdate;
use Yiisoft\ActiveRecord\Event\Handler\SoftDelete;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CategoryEventsModel;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerEventsModel;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\DefaultValueOnInsertAr;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Order;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\SetValueOnUpdateAr;
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

    public function testDeleteReturnsZeroAndSkipsDeletionWhenBeforeDeletePreventsDefault(): void
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

    public function testSetValueOnUpdateOnUpsertWithUpdatePropertiesFalse(): void
    {
        $model = new SetValueOnUpdateAr();
        $model->id = 1;
        $model->name = 'Vasya';

        $model->upsert(['id' => 1], false);

        $this->assertSame('Vasya', $model->name);
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
        EventDispatcherProvider::set(
            CustomerEventsModel::class,
            new SimpleEventDispatcher(
                static function (object $event) use (&$triggeredEvents): void {
                    $triggeredEvents[] = $event::class;
                },
            ),
        );

        $model = new CategoryEventsModel();
        unset($model->id);
        $model->name = 'After Insert';
        $model->insert();

        $model->name = 'After Save';
        $model->save();

        $model->name = 'After Update';
        $model->update();

        if ($this->db()->getDriverName() !== 'oci') {
            $model = new CustomerEventsModel();
            $model->setEmail('after-upsert@example.com');
            $model->setName('After Upsert');
            $model->upsert();
        }

        $this->assertContains(AfterInsert::class, $triggeredEvents);
        $this->assertContains(AfterSave::class, $triggeredEvents);
        $this->assertContains(AfterUpdate::class, $triggeredEvents);
        if ($this->db()->getDriverName() !== 'oci') {
            $this->assertContains(AfterUpsert::class, $triggeredEvents);
        }
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

    public function testDefaultValueOnInsertBeforeUpsertPreservesExistingInsertProperties(): void
    {
        $model = new DefaultValueOnInsertAr();
        $model->id = 7;

        $insertProperties = ['id'];
        $updateProperties = true;
        $event = new BeforeUpsert($model, $insertProperties, $updateProperties);

        $handler = new DefaultValueOnInsert('Vasya', 'name');
        $eventHandlers = $handler->getEventHandlers();
        $beforeUpsert = $eventHandlers[BeforeUpsert::class];
        $beforeUpsert($event);

        $this->assertSame(
            [0 => 'id', 'name' => 'Vasya'],
            $insertProperties,
        );
    }

    public function testSetDateTimeOnUpdateUsesCustomValue(): void
    {
        $dateTime = new DateTimeImmutable('2020-01-02 03:04:05');
        $properties = null;
        $event = new BeforeUpdate(new Order(), $properties);

        $handler = new SetDateTimeOnUpdate($dateTime, 'updated_at');
        $eventHandlers = $handler->getEventHandlers();
        $beforeUpdate = $eventHandlers[BeforeUpdate::class];
        $beforeUpdate($event);

        $this->assertSame($dateTime, $event->model->get('updated_at'));
    }

    public function testSetValueOnUpdateBeforeUpsertKeepsConfiguredPropertyNamesAndAccumulatedUpdates(): void
    {
        $model = new Customer();
        $model->setId(1);

        $insertProperties = ['id' => 1];
        $updateProperties = ['status' => 1];
        $event = new BeforeUpsert($model, $insertProperties, $updateProperties);

        $handler = new SetValueOnUpdate('Updated', 'name', 'email');
        $eventHandlers = $handler->getEventHandlers();
        $beforeUpsert = $eventHandlers[BeforeUpsert::class];
        $beforeUpsert($event);

        $this->assertSame(['name', 'email'], $handler->getPropertyNames());
        $this->assertSame(
            ['status' => 1, 'name' => 'Updated', 'email' => 'Updated'],
            $updateProperties,
        );
    }

    public function testSetValueOnUpdateBeforeUpsertUsesInsertPropertiesWithoutPrimaryKeys(): void
    {
        $model = new Customer();
        $model->setId(1);
        $model->setEmail('model@example.com');

        $insertProperties = ['id' => 1, 'email' => 'insert@example.com'];
        $updateProperties = true;
        $event = new BeforeUpsert($model, $insertProperties, $updateProperties);

        $handler = new SetValueOnUpdate('Updated', 'name');
        $eventHandlers = $handler->getEventHandlers();
        $beforeUpsert = $eventHandlers[BeforeUpsert::class];
        $beforeUpsert($event);

        $this->assertSame(
            ['email' => 'insert@example.com', 'name' => 'Updated'],
            $updateProperties,
        );
    }

    public function testSoftDeleteBeforeDeleteUsesCustomValueAndPreventsDefault(): void
    {
        $this->reloadFixtureAfterTest();

        $order = Order::query()->findByPk(1);
        $dateTime = new DateTimeImmutable('2021-02-03 04:05:06');
        $event = new BeforeDelete($order);

        $handler = new SoftDelete($dateTime, 'deleted_at');
        $eventHandlers = $handler->getEventHandlers();
        $beforeDelete = $eventHandlers[BeforeDelete::class];
        $beforeDelete($event);

        $this->assertTrue($event->isDefaultPrevented());
        $this->assertSame(1, $event->getReturnValue());
        $this->assertSame($dateTime, $order->get('deleted_at'));
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
