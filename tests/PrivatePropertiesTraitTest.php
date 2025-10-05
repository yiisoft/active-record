<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CategoryPrivatePropertiesModel;

abstract class PrivatePropertiesTraitTest extends TestCase
{
    public function testPropertyValues(): void
    {
        $model = new CategoryPrivatePropertiesModel();
        $model->setId(1);

        $values = $model->propertyValues();

        $this->assertArrayHasKey('id', $values);
        $this->assertArrayHasKey('name', $values);
        $this->assertSame(1, $values['id']);
        $this->assertNull($values['name']);
    }

    public function testPopulateRecord(): void
    {
        $model = new CategoryPrivatePropertiesModel();

        $model->populateRecord(['id' => 2, 'name' => 'Populated Category']);

        $this->assertSame(2, $model->getId());
        $this->assertSame('Populated Category', $model->getName());
    }

    public function testSetProperty(): void
    {
        $model = new CategoryPrivatePropertiesModel();

        $model->set('id', 4);
        $model->set('name', 'Set Test');

        $this->assertSame(4, $model->getId());
        $this->assertSame('Set Test', $model->getName());
    }

    public function testInsert(): void
    {
        $this->reloadFixtureAfterTest();

        $model = new CategoryPrivatePropertiesModel();
        $model->setName('Insert Test Category');

        $result = $model->insert();

        $this->assertTrue($result);
        $this->assertNotNull($model->getId());
        $this->assertSame('Insert Test Category', $model->getName());
    }

    public function testFind(): void
    {
        $model = CategoryPrivatePropertiesModel::query()->findByPk(1);

        $this->assertNotNull($model);
        $this->assertSame(1, $model->getId());
        $this->assertIsString($model->getName());
    }

    public function testUpdate(): void
    {
        $this->reloadFixtureAfterTest();

        $model = CategoryPrivatePropertiesModel::query()->findByPk(1);
        $model->setName('Updated Name');
        $result = $model->update();

        $this->assertSame(1, $result);
        $this->assertSame('Updated Name', $model->getName());

        $reloadedModel = CategoryPrivatePropertiesModel::query()->findByPk(1);
        $this->assertSame('Updated Name', $reloadedModel->getName());
    }
}
