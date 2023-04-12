<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Pgsql;

use ArrayAccess;
use Traversable;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\ArrayAndJsonTypes;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Beta;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\BoolAR;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerClosureField;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\DefaultPk;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\UserAR;
use Yiisoft\ActiveRecord\Tests\Support\PgsqlHelper;
use Yiisoft\Db\Expression\ArrayExpression;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Expression\JsonExpression;
use Yiisoft\Db\Pgsql\Schema as SchemaPgsql;

final class ActiveRecordTest extends \Yiisoft\ActiveRecord\Tests\ActiveRecordTest
{
    public function setUp(): void
    {
        parent::setUp();

        $pgsqlHelper = new PgsqlHelper();
        $this->db = $pgsqlHelper->createConnection();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->db->close();

        unset($this->db);
    }

    public function testExplicitPkOnAutoIncrement(): void
    {
        $this->checkFixture($this->db, 'customer');

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
        $this->checkFixture($this->db, 'beta');

        $betaQuery = new ActiveQuery(Beta::class, $this->db);
        $betas = $betaQuery->with('alpha')->all();
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
        $this->checkFixture($this->db, 'customer', true);

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

        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customers = $customerQuery->where(['bool_status' => true])->all();
        $this->assertCount(3, $customers);

        $customers = $customerQuery->where(['bool_status' => false])->all();
        $this->assertCount(1, $customers);
    }

    public function testBooleanValues(): void
    {
        $this->checkFixture($this->db, 'bool_values');

        $command = $this->db->createCommand();
        $command->batchInsert('bool_values', ['bool_col'], [[true], [false]])->execute();
        $boolARQuery = new ActiveQuery(BoolAR::class, $this->db);

        $this->assertTrue($boolARQuery->where(['bool_col' => true])->onePopulate()->bool_col);
        $this->assertFalse($boolARQuery->where(['bool_col' => false])->onePopulate()->bool_col);

        $this->assertEquals(1, $boolARQuery->where('bool_col = TRUE')->count('*'));
        $this->assertEquals(1, $boolARQuery->where('bool_col = FALSE')->count('*'));
        $this->assertEquals(2, $boolARQuery->where('bool_col IN (TRUE, FALSE)')->count('*'));

        $this->assertEquals(1, $boolARQuery->where(['bool_col' => true])->count('*'));
        $this->assertEquals(1, $boolARQuery->where(['bool_col' => false])->count('*'));
        $this->assertEquals(2, $boolARQuery->where(['bool_col' => [true, false]])->count('*'));

        $this->assertEquals(1, $boolARQuery->where('bool_col = :bool_col', ['bool_col' => true])->count('*'));
        $this->assertEquals(1, $boolARQuery->where('bool_col = :bool_col', ['bool_col' => false])->count('*'));
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/4672}
     */
    public function testBooleanValues2(): void
    {
        $this->checkFixture($this->db, 'bool_user');

        //$this->db->setCharset('utf8');
        $this->db->createCommand('DROP TABLE IF EXISTS bool_user;')->execute();
        $this->db->createCommand()->createTable('bool_user', [
            'id' => SchemaPgsql::TYPE_PK,
            'username' => SchemaPgsql::TYPE_STRING . ' NOT NULL',
            'auth_key' => SchemaPgsql::TYPE_STRING . '(32) NOT NULL',
            'password_hash' => SchemaPgsql::TYPE_STRING . ' NOT NULL',
            'password_reset_token' => SchemaPgsql::TYPE_STRING,
            'email' => SchemaPgsql::TYPE_STRING . ' NOT NULL',
            'role' => SchemaPgsql::TYPE_SMALLINT . ' NOT NULL DEFAULT 10',
            'status' => SchemaPgsql::TYPE_SMALLINT . ' NOT NULL DEFAULT 10',
            'created_at' => SchemaPgsql::TYPE_INTEGER . ' NOT NULL',
            'updated_at' => SchemaPgsql::TYPE_INTEGER . ' NOT NULL',
        ])->execute();
        $this->db->createCommand()->addColumn(
            'bool_user',
            'is_deleted',
            SchemaPgsql::TYPE_BOOLEAN . ' NOT NULL DEFAULT FALSE'
        )->execute();

        $user = new UserAR($this->db);
        $user->username = 'test';
        $user->auth_key = 'test';
        $user->password_hash = 'test';
        $user->email = 'test@example.com';
        $user->created_at = time();
        $user->updated_at = time();
        $user->save();

        $userQuery = new ActiveQuery(UserAR::class, $this->db);
        $this->assertCount(1, $userQuery->where(['is_deleted' => false])->all());
        $this->assertCount(0, $userQuery->where(['is_deleted' => true])->all());
        $this->assertCount(1, $userQuery->where(['is_deleted' => [true, false]])->all());
    }

    public function testBooleanDefaultValues(): void
    {
        $this->checkFixture($this->db, 'bool_values');

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
        $this->checkFixture($this->db, 'default_pk');

        $record = new DefaultPk($this->db);

        $record->type = 'type';

        $record->save();

        $this->assertEquals(5, $record->primaryKey);
    }

    public static function arrayValuesProvider(): array
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
                    null,
                ],
                'jsonarray_col' => [
                    null,
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
                    ['a' => 1, 'b' => null, 'c' => [1,3,5]],
                ],
                'jsonb_col' => [
                    new JsonExpression(new ArrayExpression([1,2,3])),
                    [1,2,3],
                ],
                'jsonarray_col' => [
                    new ArrayExpression([new JsonExpression(['1', 2]), [3,4,5]], 'json'),
                    new ArrayExpression([['1', 2], [3,4,5]], 'json'),
                ],
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
                    ['a' => 1, 'b' => null, 'c' => [1,3,5]],
                ],
                'jsonb_col' => [
                    new JsonExpression([null, 'a', 'b', '\"', '{"af"}']),
                    [null, 'a', 'b', '\"', '{"af"}'],
                ],
                'jsonarray_col' => [
                    new Expression("array['[\",\",\"null\",true,\"false\",\"f\"]'::json]::json[]"),
                    new ArrayExpression([[',', 'null', true, 'false', 'f']], 'json'),
                ],
            ]],
            'scalars' => [[
                'json_col' => [
                    '5.8',
                ],
                'jsonb_col' => [
                    M_PI,
                ],
            ]],
        ];
    }

    /**
     * @dataProvider arrayValuesProvider
     */
    public function testArrayValues($attributes): void
    {
        $this->checkFixture($this->db, 'array_and_json_types', true);

        $type = new ArrayAndJsonTypes($this->db);

        foreach ($attributes as $attribute => $expected) {
            $type->$attribute = $expected[0];
        }

        $type->save();

        $typeQuery = new ActiveQuery($type::class, $this->db);

        $type = $typeQuery->onePopulate();

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

    public function testToArray(): void
    {
        $this->checkFixture($this->db, 'customer', true);

        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customer = $customerQuery->findOne(1);

        $this->assertSame(
            [
                'id' => 1,
                'email' => 'user1@example.com',
                'name' => 'user1',
                'address' => 'address1',
                'status' => 1,
                'bool_status' => true,
                'profile_id' => 1,
            ],
            $customer->toArray(),
        );
    }

    public function testToArrayWithClosure(): void
    {
        $this->checkFixture($this->db, 'customer', true);

        $customerQuery = new ActiveQuery(CustomerClosureField::class, $this->db);
        $customer = $customerQuery->findOne(1);

        $this->assertSame(
            [
                'id' => 1,
                'email' => 'user1@example.com',
                'name' => 'user1',
                'address' => 'address1',
                'status' => 'active',
                'bool_status' => true,
                'profile_id' => 1,
            ],
            $customer->toArray(),
        );
    }
}
