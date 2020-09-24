<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Pgsql;

use ArrayAccess;
use Traversable;
use Yiisoft\ActiveRecord\Tests\ActiveRecordTest as AbstractActiveRecordTest;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\ArrayAndJsonTypes;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Beta;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\BoolAR;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\DefaultPk;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\UserAR;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Expression\ArrayExpression;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Expression\JsonExpression;
use Yiisoft\Db\Pgsql\Schema;

/**
 * @group pgsql
 */
final class ActiveRecordTest extends AbstractActiveRecordTest
{
    protected ?string $driverName = 'pgsql';
    protected ConnectionInterface $db;

    public function setUp(): void
    {
        parent::setUp();

        $this->db = $this->pgsqlConnection;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->pgsqlConnection->close();

        unset($this->pgsqlConnection);
    }

    public function testExplicitPkOnAutoIncrement()
    {
        /** @var $this TestCase|ActiveRecordTestTrait */
        $customer = new Customer($this->db);

        $customer->id = 1337;
        $customer->email = 'user1337@example.com';
        $customer->name = 'user1337';
        $customer->address = 'address1337';

        $this->assertTrue($customer->isNewRecord);

        $customer->save();

        $this->assertEquals(1337, $customer->id);
        $this->assertFalse($customer->isNewRecord);
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/15482}
     */
    public function testEagerLoadingUsingStringIdentifiers(): void
    {
        $beta = new Beta($this->db);

        $betas = $beta->find()->with('alpha')->all();

        $this->assertNotEmpty($betas);

        $alphaIdentifiers = [];

        /** @var Beta[] $betas */
        foreach ($betas as $beta) {
            $this->assertNotNull($beta->alpha);
            $this->assertEquals($beta->alpha_string_identifier, $beta->alpha->string_identifier);
            $alphaIdentifiers[] = $beta->alpha->string_identifier;
        }

        $this->assertEquals(['1', '01', '001', '001', '2', '2b', '2b', '02'], $alphaIdentifiers);
    }

    public function testBooleanAttribute(): void
    {
        $this->loadFixture($this->db);

        /** @var $this TestCase|ActiveRecordTestTrait */
        $customer = new Customer($this->db);
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

        $customers = $customer->find()->where(['bool_status' => true])->all();
        $this->assertCount(3, $customers);

        $customers = $customer->find()->where(['bool_status' => false])->all();
        $this->assertCount(1, $customers);
    }

    public function testFindAsArray(): void
    {
        $this->loadFixture($this->db);

        $customerInstance = new Customer($this->db);

        /** asArray */
        $customer = $customerInstance->find()->where(['id' => 2])->asArray()->one();
        $this->assertEquals([
            'id' => 2,
            'email' => 'user2@example.com',
            'name' => 'user2',
            'address' => 'address2',
            'status' => 1,
            'bool_status' => true,
            'profile_id' => null,
        ], $customer);

        /** find all asArray */
        $customers = $customerInstance->find()->asArray()->all();
        $this->assertCount(3, $customers);
        $this->assertArrayHasKey('id', $customers[0]);
        $this->assertArrayHasKey('name', $customers[0]);
        $this->assertArrayHasKey('email', $customers[0]);
        $this->assertArrayHasKey('address', $customers[0]);
        $this->assertArrayHasKey('status', $customers[0]);
        $this->assertArrayHasKey('bool_status', $customers[0]);
        $this->assertArrayHasKey('id', $customers[1]);
        $this->assertArrayHasKey('name', $customers[1]);
        $this->assertArrayHasKey('email', $customers[1]);
        $this->assertArrayHasKey('address', $customers[1]);
        $this->assertArrayHasKey('status', $customers[1]);
        $this->assertArrayHasKey('bool_status', $customers[1]);
        $this->assertArrayHasKey('id', $customers[2]);
        $this->assertArrayHasKey('name', $customers[2]);
        $this->assertArrayHasKey('email', $customers[2]);
        $this->assertArrayHasKey('address', $customers[2]);
        $this->assertArrayHasKey('status', $customers[2]);
        $this->assertArrayHasKey('bool_status', $customers[2]);
    }

    public function testBooleanValues(): void
    {
        $command = $this->db->createCommand();
        $command->batchInsert('bool_values', ['bool_col'], [[true], [false]])->execute();

        $boolAR = new BoolAR($this->db);

        $this->assertEquals(1, $boolAR->find()->where('bool_col = TRUE')->count('*'));
        $this->assertEquals(1, $boolAR->find()->where('bool_col = FALSE')->count('*'));
        $this->assertEquals(2, $boolAR->find()->where('bool_col IN (TRUE, FALSE)')->count('*'));

        $this->assertEquals(1, $boolAR->find()->where(['bool_col' => true])->count('*'));
        $this->assertEquals(1, $boolAR->find()->where(['bool_col' => false])->count('*'));
        $this->assertEquals(2, $boolAR->find()->where(['bool_col' => [true, false]])->count('*'));

        $this->assertEquals(1, $boolAR->find()->where('bool_col = :bool_col', ['bool_col' => true])->count('*'));
        $this->assertEquals(1, $boolAR->find()->where('bool_col = :bool_col', ['bool_col' => false])->count('*'));

        $this->assertTrue($boolAR->find()->where(['bool_col' => true])->one()->bool_col);
        $this->assertFalse($boolAR->find()->where(['bool_col' => false])->one()->bool_col);
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/4672}
     */
    public function testBooleanValues2(): void
    {
        $this->db->setCharset('utf8');

        $this->db->createCommand('DROP TABLE IF EXISTS bool_user;')->execute();

        $this->db->createCommand()->createTable('bool_user', [
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

        $this->db->createCommand()->addColumn(
            'bool_user',
            'is_deleted',
            Schema::TYPE_BOOLEAN . ' NOT NULL DEFAULT FALSE'
        )->execute();

        $user = new UserAR($this->db);
        $user->username = 'test';
        $user->auth_key = 'test';
        $user->password_hash = 'test';
        $user->email = 'test@example.com';
        $user->created_at = time();
        $user->updated_at = time();

        $user->save();

        $this->assertCount(1, $user->find()->where(['is_deleted' => false])->all());
        $this->assertCount(0, $user->find()->where(['is_deleted' => true])->all());
        $this->assertCount(1, $user->find()->where(['is_deleted' => [true, false]])->all());
    }

    public function testBooleanDefaultValues(): void
    {
        $arClass = new BoolAR($this->db);
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
        $record = new DefaultPk($this->db);

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
                    pi()
                ],
            ]],
        ];
    }

    /**
     * @dataProvider arrayValuesProvider $attributes
     */
    public function testArrayValues($attributes): void
    {
        $this->loadFixture($this->db);

        $type = new ArrayAndJsonTypes($this->db);

        foreach ($attributes as $attribute => $expected) {
            $type->$attribute = $expected[0];
        }

        $type->save();

        $type = $type->find()->one();

        foreach ($attributes as $attribute => $expected) {
            $expected = isset($expected[1]) ? $expected[1] : $expected[0];
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
