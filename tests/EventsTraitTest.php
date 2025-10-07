<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\Event\BeforeCreateQuery;
use Yiisoft\ActiveRecord\Event\BeforeInsert;
use Yiisoft\ActiveRecord\Event\BeforePopulate;
use Yiisoft\ActiveRecord\Event\BeforeSave;
use Yiisoft\ActiveRecord\Event\BeforeUpdate;
use Yiisoft\ActiveRecord\Event\BeforeUpsert;
use Yiisoft\ActiveRecord\Event\EventDispatcherProvider;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Category;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CategoryEventsModel;
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
                }
            )
        );

        $model = new CategoryEventsModel();
        $model->name = 'Prevented Category';

        $result = $model->insert();

        $this->assertFalse($result);
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
                }
            )
        );

        $model = new CategoryEventsModel();
        $model->name = 'Custom Return Category';

        $result = $model->insert();

        $this->assertTrue($result);
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
                }
            )
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
                }
            )
        );

        $query = CategoryEventsModel::query();

        $this->assertSame($customQuery, $query);
    }

    public function testQueryWithClosureModelClass(): void
    {
        $query = CategoryEventsModel::query(fn() => new Category());

        $this->assertInstanceOf(Category::class, $query->getModel());
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
                }
            )
        );

        $model = new CategoryEventsModel();
        $model->name = 'Prevented Save';

        $result = $model->save();

        $this->assertFalse($result);
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
                }
            )
        );

        $model = new CategoryEventsModel();
        $model->name = 'Custom Return Save';

        $result = $model->save();

        $this->assertTrue($result);
        $this->assertNull($model->id);
        $this->assertSame('Custom Return Save', $model->name);
    }

    public function testUpdateWithEventPrevention(): void
    {
        $this->reloadFixtureAfterTest();

        EventDispatcherProvider::set(
            CategoryEventsModel::class,
            new SimpleEventDispatcher(
                static function (object $event): void {
                    if ($event instanceof BeforeUpdate) {
                        $event->preventDefault();
                    }
                }
            )
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
                }
            )
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
        $this->reloadFixtureAfterTest();

        EventDispatcherProvider::set(
            CategoryEventsModel::class,
            new SimpleEventDispatcher(
                static function (object $event): void {
                    if ($event instanceof BeforeUpsert) {
                        $event->preventDefault();
                    }
                }
            )
        );

        $model = new CategoryEventsModel();
        $model->name = 'Prevented Upsert';

        $result = $model->upsert();

        $this->assertFalse($result);
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
                }
            )
        );

        $model = new CategoryEventsModel();
        $model->name = 'Custom Return Upsert';

        $result = $model->upsert();

        $this->assertTrue($result);
        $this->assertNull($model->id);
        $this->assertSame('Custom Return Upsert', $model->name);
    }
}
