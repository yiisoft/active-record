<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\Models\DynamicModel;
use Yiisoft\ActiveRecord\Tests\TestCase;
use Yiisoft\Db\Exception\UnknownPropertyException;

final class DynamicModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

     public function testDynamicProperty()
    {
        $email = 'invalid';
        $name = 'long name';

        $model = new DynamicModel(compact('name', 'email'));

        $this->assertEquals($email, $model->email);
        $this->assertEquals($name, $model->name);
        $this->assertTrue($model->canGetProperty('email'));
        $this->assertTrue($model->canGetProperty('name'));
        $this->assertTrue($model->canSetProperty('email'));
        $this->assertTrue($model->canSetProperty('name'));

        $this->expectException(UnknownPropertyException::class);

        $age = $model->age;
    }

    public function testLoad()
    {
        $dynamic = new DynamicModel();

        //define two attributes
        $dynamic->defineAttribute('name');
        $dynamic->defineAttribute('mobile');

        // define your sample data
        $data = [
            'DynamicModel' => [
                'name' => $name = 'your name 2',
                'mobile' => $mobile = 'my number mobile',
            ],
        ];

        // load data
        $this->assertFalse($dynamic->load([]));
        $this->assertTrue($dynamic->load($data));
    }
}
