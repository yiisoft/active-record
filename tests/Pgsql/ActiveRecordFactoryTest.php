<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Pgsql;

use ArrayAccess;
use Traversable;
use Yiisoft\ActiveRecord\Tests\ActiveRecordFactoryTest as AbstractActiveRecordFactoryTest;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\ArrayAndJsonTypes;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\BoolAR;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\DefaultPk;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\UserAR;
use Yiisoft\Db\Expression\ArrayExpression;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Expression\JsonExpression;
use Yiisoft\Db\Pgsql\Schema;

/**
 * @group pgsql
 */
final class ActiveRecordFactoryTest extends AbstractActiveRecordFactoryTest
{
    protected string $driverName = 'pgsql';

    public function setUp(): void
    {
        parent::setUp();

        $this->arFactory->withConnection($this->pgsqlConnection);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->pgsqlConnection->close();

        unset($this->arFactory, $this->pgsqlConnection);
    }

    public function testExplicitPkOnAutoIncrement(): void
    {
        $customer = $this->arFactory->createAR(Customer::class);

        $customer->id = 1337;
        $customer->email = 'user1337@example.com';
        $customer->name = 'user1337';
        $customer->address = 'address1337';

        $this->assertTrue($customer->isNewRecord);

        $customer->save();

        $this->assertEquals(1337, $customer->id);
        $this->assertFalse($customer->isNewRecord);
    }

    public function testBooleanAttribute(): void
    {
        $this->loadFixture($this->pgsqlConnection);

        $customer = $this->arFactory->createAR(Customer::class);
        $customer->name = 'boolean customer';
        $customer->email = 'mail@example.com';
        $customer->bool_status = false;

        $customer->save();

        $customer->refresh();
        $this->assertFalse($customer->bool_status);

        $customer->bool_status = true;

        $customer->save();
        $customer->refresh();
        $this->assertTrue($customer->bool_status);

        $customerQuery = $this->arFactory->createQueryTo(Customer::class);
        $customers = $customerQuery->where(['bool_status' => true])->all();
        $this->assertCount(3, $customers);

        $customers = $customerQuery->where(['bool_status' => false])->all();
        $this->assertCount(1, $customers);
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/4672}
     */
    public function testBooleanValues2(): void
    {
        $this->pgsqlConnection->setCharset('utf8');

        $this->pgsqlConnection->createCommand('DROP TABLE IF EXISTS bool_user;')->execute();

        $this->pgsqlConnection->createCommand()->createTable('bool_user', [
            'id' => Schema::TYPE_PK,
            'username' => Schema::TYPE_STRING . ' NOT NULL',
            'auth_key' => Schema::TYPE_STRING . '(32) NOT NULL',
            'password_hash' => Schema::TYPE_STRING . ' NOT NULL',
            'password_reset_token' => Schema::TYPE_STRING,
            'email' => Schema::TYPE_STRING . ' NOT NULL',
            'role' => Schema::TYPE_SMALLINT . ' NOT NULL DEFAULT 10',
            'status' => Schema::TYPE_SMALLINT . ' NOT NULL DEFAULT 10',
            'created_at' => Schema::TYPE_INTEGER . ' NOT NULL',
            'updated_at' => Schema::TYPE_INTEGER . ' NOT NULL',
        ])->execute();

        $this->pgsqlConnection->createCommand()->addColumn(
            'bool_user',
            'is_deleted',
            Schema::TYPE_BOOLEAN . ' NOT NULL DEFAULT FALSE'
        )->execute();

        $user = $this->arFactory->createAR(UserAR::class);

        $user->username = 'test';
        $user->auth_key = 'test';
        $user->password_hash = 'test';
        $user->email = 'test@example.com';
        $user->created_at = time();
        $user->updated_at = time();

        $user->save();

        $userQuery = $this->arFactory->createQueryTo(UserAR::class);

        $this->assertCount(1, $userQuery->where(['is_deleted' => false])->all());
        $this->assertCount(0, $userQuery->where(['is_deleted' => true])->all());
        $this->assertCount(1, $userQuery->where(['is_deleted' => [true, false]])->all());
    }

    public function testBooleanDefaultValues(): void
    {
        $arClass = $this->arFactory->createAR(BoolAR::class);

        $this->assertNull($arClass->bool_col);
        $this->assertNull($arClass->default_true);
        $this->assertNull($arClass->default_false);

        $arClass->loadDefaultValues();

        $this->assertNull($arClass->bool_col);
        $this->assertTrue($arClass->default_true);
        $this->assertFalse($arClass->default_false);
        $this->assertTrue($arClass->save());
    }

    public function testPrimaryKeyAfterSave(): void
    {
        $record = $this->arFactory->createAR(DefaultPk::class);

        $record->type = 'type';

        $record->save();

        $this->assertEquals(5, $record->primaryKey);
    }

    public function arrayValuesProvider(): array
    {
        return [
            'simple arrays values' => [[
                'intarray_col' => [
                    new ArrayExpression([1,-2,null,'42'], 'int4', 1),
                    new ArrayExpression([1,-2,null,42], 'int4', 1),
                ],
                'textarray2_col' => [
                    new ArrayExpression([['text'], [null], [1]], 'text', 2),
                    new ArrayExpression([['text'], [null], ['1']], 'text', 2),
                ],
                'json_col' => [['a' => 1, 'b' => null, 'c' => [1,3,5]]],
                'jsonb_col' => [[null, 'a', 'b', '\"', '{"af"}']],
                'jsonarray_col' => [new ArrayExpression([[',', 'null', true, 'false', 'f']], 'json')],
            ]],
            'null arrays values' => [[
                'intarray_col' => [
                    null,
                ],
                'textarray2_col' => [
                    [null, null],
                    new ArrayExpression([null, null], 'text', 2),
                ],
                'json_col' => [
                    null
                ],
                'jsonarray_col' => [
                    null
                ],
            ]],
            'empty arrays values' => [[
                'textarray2_col' => [
                    [[], []],
                    new ArrayExpression([], 'text', 2),
                ],
            ]],
            'nested objects' => [[
                'intarray_col' => [
                    new ArrayExpression(new ArrayExpression([1,2,3]), 'int', 1),
                    new ArrayExpression([1,2,3], 'int4', 1),
                ],
                'textarray2_col' => [
                    new ArrayExpression([new ArrayExpression(['text']), [null], [1]], 'text', 2),
                    new ArrayExpression([['text'], [null], ['1']], 'text', 2),
                ],
                'json_col' => [
                    new JsonExpression(new JsonExpression(new JsonExpression(['a' => 1, 'b' => null, 'c' => new JsonExpression([1,3,5])]))),
                    ['a' => 1, 'b' => null, 'c' => [1,3,5]]
                ],
                'jsonb_col' => [
                    new JsonExpression(new ArrayExpression([1,2,3])),
                    [1,2,3]
                ],
                'jsonarray_col' => [
                    new ArrayExpression([new JsonExpression(['1', 2]), [3,4,5]], 'json'),
                    new ArrayExpression([['1', 2], [3,4,5]], 'json')
                ]
            ]],
            'arrays packed in classes' => [[
                'intarray_col' => [
                    new ArrayExpression([1,-2,null,'42'], 'int', 1),
                    new ArrayExpression([1,-2,null,42], 'int4', 1),
                ],
                'textarray2_col' => [
                    new ArrayExpression([['text'], [null], [1]], 'text', 2),
                    new ArrayExpression([['text'], [null], ['1']], 'text', 2),
                ],
                'json_col' => [
                    new JsonExpression(['a' => 1, 'b' => null, 'c' => [1,3,5]]),
                    ['a' => 1, 'b' => null, 'c' => [1,3,5]]
                ],
                'jsonb_col' => [
                    new JsonExpression([null, 'a', 'b', '\"', '{"af"}']),
                    [null, 'a', 'b', '\"', '{"af"}']
                ],
                'jsonarray_col' => [
                    new Expression("array['[\",\",\"null\",true,\"false\",\"f\"]'::json]::json[]"),
                    new ArrayExpression([[',', 'null', true, 'false', 'f']], 'json'),
                ]
            ]],
            'scalars' => [[
                'json_col' => [
                    '5.8',
                ],
                'jsonb_col' => [
                    M_PI
                ],
            ]],
        ];
    }

    /**
     * @dataProvider arrayValuesProvider $attributes
     */
    public function testArrayValues($attributes): void
    {
        $this->loadFixture($this->pgsqlConnection);

        $type = $this->arFactory->createAR(ArrayAndJsonTypes::class);

        foreach ($attributes as $attribute => $expected) {
            $type->$attribute = $expected[0];
        }

        $type->save();

        $typeQuery = $this->arFactory->createQueryTo(get_class($type));

        $type = $typeQuery->one();

        foreach ($attributes as $attribute => $expected) {
            $expected = $expected[1] ?? $expected[0];
            $value = $type->$attribute;

            if ($expected instanceof ArrayExpression) {
                $expected = $expected->getValue();
            }

            $this->assertEquals($expected, $value, 'In column ' . $attribute);

            if ($value instanceof ArrayExpression) {
                $this->assertInstanceOf(ArrayAccess::class, $value);
                $this->assertInstanceOf(Traversable::class, $value);
                /** testing arrayaccess */
                foreach ($type->$attribute as $key => $v) {
                    $this->assertSame($expected[$key], $value[$key]);
                }
            }
        }

        /** Testing update */
        foreach ($attributes as $attribute => $expected) {
            $type->markAttributeDirty($attribute);
        }

        $this->assertSame(1, $type->update(), 'The record got updated');
    }
}
