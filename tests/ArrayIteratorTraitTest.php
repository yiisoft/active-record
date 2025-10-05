<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CategoryArrayIteratorModel;

abstract class ArrayIteratorTraitTest extends TestCase
{
    public function testIteratorReturnsPropertyValues(): void
    {
        $model = new CategoryArrayIteratorModel();
        $model->id = 1;
        $model->name = 'Test Category';

        $iterator = $model->getIterator();
        $arrayData = $iterator->getArrayCopy();

        $this->assertCount(2, $arrayData);
        $this->assertSame(1, $arrayData['id']);
        $this->assertSame('Test Category', $arrayData['name']);
    }

    public function testIteratorWithNullValues(): void
    {
        $model = new CategoryArrayIteratorModel();
        $model->id = null;
        $model->name = null;

        $iterator = $model->getIterator();
        $arrayData = $iterator->getArrayCopy();

        $this->assertNull($arrayData['id']);
        $this->assertNull($arrayData['name']);
    }

    public function testForeachIteration(): void
    {
        $model = new CategoryArrayIteratorModel();
        $model->id = 2;
        $model->name = 'Foreach Category';

        $iteratedData = [];
        foreach ($model as $property => $value) {
            $iteratedData[$property] = $value;
        }

        $this->assertSame(2, $iteratedData['id']);
        $this->assertSame('Foreach Category', $iteratedData['name']);
    }

    public function testIteratorCount(): void
    {
        $model = new CategoryArrayIteratorModel();
        $model->id = 1;
        $model->name = 'Test Category';

        $iterator = $model->getIterator();

        $this->assertSame(2, $iterator->count());
    }

    public function testIteratorIsValid(): void
    {
        $model = new CategoryArrayIteratorModel();
        $model->id = 1;
        $model->name = 'Test Category';

        $iterator = $model->getIterator();

        $this->assertTrue($iterator->valid());
        $this->assertSame('id', $iterator->key());
        $this->assertSame(1, $iterator->current());

        $iterator->next();
        $this->assertTrue($iterator->valid());
        $this->assertSame('name', $iterator->key());
        $this->assertSame('Test Category', $iterator->current());

        $iterator->next();
        $this->assertFalse($iterator->valid());
    }

    public function testIteratorRewind(): void
    {
        $model = new CategoryArrayIteratorModel();
        $model->id = 1;
        $model->name = 'Test Category';

        $iterator = $model->getIterator();
        $iterator->next();
        $iterator->next();
        $this->assertFalse($iterator->valid());

        $iterator->rewind();
        $this->assertTrue($iterator->valid());
        $this->assertSame('id', $iterator->key());
        $this->assertSame(1, $iterator->current());
    }

    public function testIteratorSeek(): void
    {
        $model = new CategoryArrayIteratorModel();
        $model->id = 1;
        $model->name = 'Test Category';

        $iterator = $model->getIterator();
        $iterator->seek(1);

        $this->assertTrue($iterator->valid());
        $this->assertSame('name', $iterator->key());
        $this->assertSame('Test Category', $iterator->current());
    }

    public function testMultipleIteratorsIndependent(): void
    {
        $model = new CategoryArrayIteratorModel();
        $model->id = 1;
        $model->name = 'Test Category';

        $iterator1 = $model->getIterator();
        $iterator2 = $model->getIterator();

        $iterator1->next();
        $this->assertSame('name', $iterator1->key());
        $this->assertSame('id', $iterator2->key());
    }
}
