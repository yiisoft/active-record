<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use InvalidArgumentException;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerArrayAccessModel;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Order;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Profile;

abstract class ArrayAccessTraitTest extends TestCase
{
    public function testOffsetExists(): void
    {
        $model = new CustomerArrayAccessModel();
        $model->name = 'test';
        $model->customProperty = 'value';

        $this->assertTrue(isset($model['name']));
        $this->assertTrue(isset($model['customProperty']));
        $this->assertFalse(isset($model['email']));
        $this->assertFalse(isset($model['not-exists']));
    }

    public function testOffsetExistsWithRelation(): void
    {
        $model = CustomerArrayAccessModel::query()->with('profile')->findByPk(1);

        $this->assertTrue(isset($model['profile']));
    }

    public function testOffsetGet(): void
    {
        $model = new CustomerArrayAccessModel();
        $model->name = 'test name';
        $model->customProperty = 'custom value';

        $this->assertSame('test name', $model['name']);
        $this->assertNull($model['email']);
        $this->assertSame('custom value', $model['customProperty']);
    }

    public function testOffsetGetWithRelation(): void
    {
        $this->reloadFixtureAfterTest();

        $model = CustomerArrayAccessModel::query()->with('profile')->findByPk(1);

        $this->assertInstanceOf(Profile::class, $model['profile']);
    }

    public function testOffsetGetWithNonExistentProperty(): void
    {
        $model = new CustomerArrayAccessModel();

        $this->expectException(InvalidArgumentException::class);
        $model['nonexistent'];
    }

    public function testOffsetSetWithProperty(): void
    {
        $model = new CustomerArrayAccessModel();
        $model['name'] = 'new name';
        $model['customProperty'] = 'new custom value';

        $this->assertSame('new name', $model->name);
        $this->assertSame('new custom value', $model->customProperty);
    }

    public function testOffsetSetWithActiveRecordRelation(): void
    {
        $model = new CustomerArrayAccessModel();
        $profile = new Profile();

        $model['profile'] = $profile;

        $this->assertSame($profile, $model->relation('profile'));
    }

    public function testOffsetSetWithArrayRelation(): void
    {
        $model = new CustomerArrayAccessModel();
        $orders = [new Order(), new Order()];

        $model['orders'] = $orders;

        $this->assertSame($orders, $model->relation('orders'));
    }

    public function testOffsetSetWithNullRelation(): void
    {
        $model = new CustomerArrayAccessModel();

        $model['profile'] = null;

        $this->assertNull($model->relation('profile'));
    }

    public function testOffsetSetWithInvalidValue(): void
    {
        $model = new CustomerArrayAccessModel();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Setting unknown property: ' . CustomerArrayAccessModel::class . '::unknown_property');
        $model['unknown_property'] = 'invalid';
    }

    public function testOffsetUnsetWithProperty(): void
    {
        $model = new CustomerArrayAccessModel();
        $model->name = 'test';

        unset($model['name']);

        $this->assertTrue(!isset($model->name));
    }

    public function testOffsetUnsetWithObjectProperty(): void
    {
        $model = new CustomerArrayAccessModel();
        $model->customProperty = 'test';

        unset($model['customProperty']);

        $this->assertTrue(!isset($model->customProperty));
    }

    public function testOffsetUnsetWithRelation(): void
    {
        $this->reloadFixtureAfterTest();

        $model = CustomerArrayAccessModel::query()->with('profile')->findByPk(1);
        $this->assertTrue($model->isRelationPopulated('profile'));

        unset($model['profile']);

        $this->assertFalse($model->isRelationPopulated('profile'));
    }
}
