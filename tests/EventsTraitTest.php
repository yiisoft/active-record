<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\Event\BeforeInsert;
use Yiisoft\ActiveRecord\Event\BeforePopulate;
use Yiisoft\ActiveRecord\Event\EventDispatcherProvider;
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
        $this->reloadFixtureAfterTest();

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
        $this->reloadFixtureAfterTest();

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
}
