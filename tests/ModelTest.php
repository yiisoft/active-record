<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\Models\Model;
use Yiisoft\ActiveRecord\Tests\TestCase;
use Yiisoft\ActiveRecord\Tests\Data\RulesModel;
use Yiisoft\ActiveRecord\Tests\Data\Singer;
use Yiisoft\ActiveRecord\Tests\Data\Speaker;
use Yiisoft\Db\Exception\InvalidConfigException;

final class ModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testGetAttributeLabel(): void
    {
        $speaker = new Speaker();

        $this->assertEquals('First Name', $speaker->getAttributeLabel('firstName'));
        $this->assertEquals('This is the custom label', $speaker->getAttributeLabel('customLabel'));
        $this->assertEquals('Underscore Style', $speaker->getAttributeLabel('underscore_style'));
    }

    public function testGetAttributes(): void
    {
        $speaker = new Speaker();

        $speaker->firstName = 'Qiang';
        $speaker->lastName = 'Xue';

        $this->assertEquals([
            'firstName' => 'Qiang',
            'lastName' => 'Xue',
            'customLabel' => null,
            'underscore_style' => null,
        ], $speaker->getAttributes());

        $this->assertEquals([
            'firstName' => 'Qiang',
            'lastName' => 'Xue',
        ], $speaker->getAttributes(['firstName', 'lastName']));

        $this->assertEquals([
            'firstName' => 'Qiang',
            'lastName' => 'Xue',
        ], $speaker->getAttributes(null, ['customLabel', 'underscore_style']));

        $this->assertEquals([
            'firstName' => 'Qiang',
        ], $speaker->getAttributes(['firstName', 'lastName'], ['lastName', 'customLabel', 'underscore_style']));
    }

    public function testSetAttributes(): void
    {
        // by default mass assignment doesn't work at all
        $speaker = new Speaker();

        $speaker->setAttributes(['firstName' => 'Qiang', 'underscore_style' => 'test']);
        $this->assertNull($speaker->firstName);
        $this->assertNull($speaker->underscore_style);

        // in the test scenario
        $speaker = new Speaker();

        $speaker->setScenario('test');
        $speaker->setAttributes(['firstName' => 'Qiang', 'underscore_style' => 'test']);

        $this->assertNull($speaker->underscore_style);
        $this->assertEquals('Qiang', $speaker->firstName);

        $speaker->setAttributes(['firstName' => 'Qiang', 'underscore_style' => 'test'], false);
        $this->assertEquals('test', $speaker->underscore_style);
        $this->assertEquals('Qiang', $speaker->firstName);
    }

    public function testLoad(): void
    {
        $singer = new Singer();
        $this->assertEquals('Singer', $singer->formName());

        $post = ['firstName' => 'Qiang'];

        Speaker::$formName = '';

        $model = new Speaker();
        $model->setScenario('test');
        $this->assertTrue($model->load($post));
        $this->assertEquals('Qiang', $model->firstName);

        Speaker::$formName = 'Speaker';
        $model = new Speaker();
        $model->setScenario('test');
        $this->assertTrue($model->load(['Speaker' => $post]));
        $this->assertEquals('Qiang', $model->firstName);

        Speaker::$formName = 'Speaker';
        $model = new Speaker();
        $model->setScenario('test');
        $this->assertFalse($model->load(['Example' => []]));
        $this->assertEquals('', $model->firstName);
    }

    public function testLoadMultiple(): void
    {
        $data = [
            ['firstName' => 'Thomas', 'lastName' => 'Anderson'],
            ['firstName' => 'Agent', 'lastName' => 'Smith'],
        ];

        Speaker::$formName = '';

        $neo = new Speaker();

        $neo->setScenario('test');

        $smith = new Speaker();

        $smith->setScenario('test');

        $this->assertTrue(Speaker::loadMultiple([$neo, $smith], $data));
        $this->assertEquals('Thomas', $neo->firstName);
        $this->assertEquals('Smith', $smith->lastName);

        Speaker::$formName = 'Speaker';

        $neo = new Speaker();

        $neo->setScenario('test');

        $smith = new Speaker();

        $smith->setScenario('test');

        $this->assertTrue(Speaker::loadMultiple([$neo, $smith], ['Speaker' => $data], 'Speaker'));
        $this->assertEquals('Thomas', $neo->firstName);
        $this->assertEquals('Smith', $smith->lastName);

        Speaker::$formName = 'Speaker';

        $neo = new Speaker();

        $neo->setScenario('test');

        $smith = new Speaker();

        $smith->setScenario('test');

        $this->assertFalse(Speaker::loadMultiple([$neo, $smith], ['Speaker' => $data], 'Morpheus'));
        $this->assertEquals('', $neo->firstName);
        $this->assertEquals('', $smith->lastName);
    }

    public function testActiveAttributes(): void
    {
        // by default mass assignment doesn't work at all
        $speaker = new Speaker();
        $this->assertEmpty($speaker->activeAttributes());

        $speaker = new Speaker();
        $speaker->setScenario('test');
        $this->assertEquals(['firstName', 'lastName', 'underscore_style'], $speaker->activeAttributes());
    }

    public function testActiveAttributesAreUnique(): void
    {
        // by default mass assignment doesn't work at all
        $speaker = new Speaker();
        $this->assertEmpty($speaker->activeAttributes());

        $speaker = new Speaker();
        $speaker->setScenario('duplicates');
        $this->assertEquals(['firstName', 'underscore_style'], $speaker->activeAttributes());
    }

    public function testErrors(): void
    {
        $speaker = new Speaker();

        $this->assertEmpty($speaker->getErrors());
        $this->assertEmpty($speaker->getErrors('firstName'));
        $this->assertEmpty($speaker->getFirstErrors());
        $this->assertFalse($speaker->hasErrors());
        $this->assertFalse($speaker->hasErrors('firstName'));

        $speaker->addError('firstName', 'Something is wrong!');
        $this->assertEquals(['firstName' => ['Something is wrong!']], $speaker->getErrors());
        $this->assertEquals(['Something is wrong!'], $speaker->getErrors('firstName'));

        $speaker->addError('firstName', 'Totally wrong!');
        $this->assertEquals(['firstName' => ['Something is wrong!', 'Totally wrong!']], $speaker->getErrors());
        $this->assertEquals(['Something is wrong!', 'Totally wrong!'], $speaker->getErrors('firstName'));
        $this->assertFalse($speaker->hasErrors('lastName'));
        $this->assertTrue($speaker->hasErrors());
        $this->assertTrue($speaker->hasErrors('firstName'));

        $this->assertEquals(['firstName' => 'Something is wrong!'], $speaker->getFirstErrors());
        $this->assertEquals('Something is wrong!', $speaker->getFirstError('firstName'));
        $this->assertNull($speaker->getFirstError('lastName'));

        $speaker->addError('lastName', 'Another one!');
        $this->assertEquals([
            'firstName' => [
                'Something is wrong!',
                'Totally wrong!',
            ],
            'lastName' => ['Another one!'],
        ], $speaker->getErrors());

        $this->assertEquals(['Another one!', 'Something is wrong!', 'Totally wrong!'], $speaker->getErrorSummary(true));
        $this->assertEquals(['Another one!', 'Something is wrong!'], $speaker->getErrorSummary(false));

        $speaker->clearErrors('firstName');
        $this->assertEquals([
            'lastName' => ['Another one!'],
        ], $speaker->getErrors());

        $speaker->clearErrors();
        $this->assertEmpty($speaker->getErrors());
        $this->assertFalse($speaker->hasErrors());
    }

    public function testAddErrors(): void
    {
        $singer = new Singer();

        $errors = ['firstName' => ['Something is wrong!']];
        $singer->addErrors($errors);
        $this->assertEquals($singer->getErrors(), $errors);

        $singer->clearErrors();
        $singer->addErrors(['firstName' => 'Something is wrong!']);
        $this->assertEquals($singer->getErrors(), ['firstName' => ['Something is wrong!']]);

        $singer->clearErrors();
        $errors = ['firstName' => ['Something is wrong!', 'Totally wrong!']];
        $singer->addErrors($errors);
        $this->assertEquals($singer->getErrors(), $errors);

        $singer->clearErrors();
        $errors = [
            'firstName' => ['Something is wrong!'],
            'lastName' => ['Another one!'],
        ];
        $singer->addErrors($errors);
        $this->assertEquals($singer->getErrors(), $errors);

        $singer->clearErrors();
        $errors = [
            'firstName' => ['Something is wrong!', 'Totally wrong!'],
            'lastName' => ['Another one!'],
        ];
        $singer->addErrors($errors);
        $this->assertEquals($singer->getErrors(), $errors);

        $singer->clearErrors();
        $errors = [
            'firstName' => ['Something is wrong!', 'Totally wrong!'],
            'lastName' => ['Another one!', 'Totally wrong!'],
        ];
        $singer->addErrors($errors);
        $this->assertEquals($singer->getErrors(), $errors);
    }

    public function testArraySyntax(): void
    {
        $speaker = new Speaker();

        // get
        $this->assertNull($speaker['firstName']);

        // isset
        $this->assertFalse(isset($speaker['firstName']));
        $this->assertFalse(isset($speaker['unExistingField']));

        // set
        $speaker['firstName'] = 'Qiang';

        $this->assertEquals('Qiang', $speaker['firstName']);
        $this->assertTrue(isset($speaker['firstName']));

        // iteration
        $attributes = [];

        foreach ($speaker as $key => $attribute) {
            $attributes[$key] = $attribute;
        }
        $this->assertEquals([
            'firstName' => 'Qiang',
            'lastName' => null,
            'customLabel' => null,
            'underscore_style' => null,
        ], $attributes);

        // unset
        unset($speaker['firstName']);

        // exception isn't expected here
        $this->assertNull($speaker['firstName']);
        $this->assertFalse(isset($speaker['firstName']));
    }

    public function testDefaults(): void
    {
        $singer = new Model();

        $this->assertEquals([], $singer->rules());
        $this->assertEquals([], $singer->attributeLabels());
    }

    public function testFormNameWithAnonymousClass(): void
    {
        $model = require __DIR__ . '/data/AnonymousModelClass.php';

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('The "formName()" method should be explicitly defined for anonymous models');

        $model->formName();
    }
}
