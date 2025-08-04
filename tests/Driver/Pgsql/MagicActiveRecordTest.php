<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Pgsql;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\ArrayAndJsonTypes;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\Beta;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\BoolAR;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\DefaultPk;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\UserAR;
use Yiisoft\ActiveRecord\Tests\Support\PgsqlHelper;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Expression\ArrayExpression;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Expression\JsonExpression;
use Yiisoft\Db\Pgsql\Schema as SchemaPgsql;

final class MagicActiveRecordTest extends \Yiisoft\ActiveRecord\Tests\MagicActiveRecordTest
{
    protected static function createConnection(): ConnectionInterface
    {
        return (new PgsqlHelper())->createConnection();
    }

    public function testExplicitPkOnAutoIncrement(): void
    {
        $this->reloadFixtureAfterTest();

        $customer = new Customer();
        $customer->id = 1337;
        $customer->email = 'user1337@example.com';
        $customer->name = 'user1337';
        $customer->address = 'address1337';
        $this->assertTrue($customer->isNewRecord());

        $customer->save();
        $this->assertEquals(1337, $customer->id);
        $this->assertFalse($customer->isNewRecord());
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/15482}
     */
    public function testEagerLoadingUsingStringIdentifiers(): void
    {
        $betaQuery = Beta::query();
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

    public function testBooleanProperty(): void
    {
        $this->reloadFixtureAfterTest();

        $customer = new Customer();
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

        $customerQuery = Customer::query();
        $customers = $customerQuery->where(['bool_status' => true])->all();
        $this->assertCount(3, $customers);

        $customers = $customerQuery->setWhere(['bool_status' => false])->all();
        $this->assertCount(1, $customers);
    }

    public function testBooleanValues(): void
    {
        $command = $this->db()->createCommand();
        $command->batchInsert('bool_values', ['bool_col'], [[true], [false]])->execute();
        $boolARQuery = BoolAR::query();

        $this->assertTrue($boolARQuery->where(['bool_col' => true])->one()->bool_col);
        $this->assertFalse($boolARQuery->setWhere(['bool_col' => false])->one()->bool_col);

        $this->assertEquals(1, $boolARQuery->setWhere('bool_col = TRUE')->count('*'));
        $this->assertEquals(1, $boolARQuery->setWhere('bool_col = FALSE')->count('*'));
        $this->assertEquals(2, $boolARQuery->setWhere('bool_col IN (TRUE, FALSE)')->count('*'));

        $this->assertEquals(1, $boolARQuery->setWhere(['bool_col' => true])->count('*'));
        $this->assertEquals(1, $boolARQuery->setWhere(['bool_col' => false])->count('*'));
        $this->assertEquals(2, $boolARQuery->setWhere(['bool_col' => [true, false]])->count('*'));

        $this->assertEquals(1, $boolARQuery->setWhere('bool_col = :bool_col', ['bool_col' => true])->count('*'));
        $this->assertEquals(1, $boolARQuery->setWhere('bool_col = :bool_col', ['bool_col' => false])->count('*'));
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/4672}
     */
    public function testBooleanValues2(): void
    {
        $this->reloadFixtureAfterTest();

        //$this->db()->setCharset('utf8');
        $this->db()->createCommand('DROP TABLE IF EXISTS bool_user;')->execute();
        $this->db()->createCommand()->createTable('bool_user', [
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
        $this->db()->createCommand()->addColumn(
            'bool_user',
            'is_deleted',
            SchemaPgsql::TYPE_BOOLEAN . ' NOT NULL DEFAULT FALSE'
        )->execute();

        $user = new UserAR();
        $user->username = 'test';
        $user->auth_key = 'test';
        $user->password_hash = 'test';
        $user->email = 'test@example.com';
        $user->created_at = time();
        $user->updated_at = time();
        $user->save();

        $userQuery = UserAR::query();
        $this->assertCount(1, $userQuery->where(['is_deleted' => false])->all());
        $this->assertCount(0, $userQuery->setWhere(['is_deleted' => true])->all());
        $this->assertCount(1, $userQuery->setWhere(['is_deleted' => [true, false]])->all());
    }

    public function testBooleanDefaultValues(): void
    {
        $arClass = new BoolAR();

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
        $this->reloadFixtureAfterTest();

        $record = new DefaultPk();

        $record->type = 'type';

        $record->save();

        $this->assertEquals(5, $record->primaryKeyValue());
    }

    public static function arrayValuesProvider(): array
    {
        return [
            'simple arrays values' => [[
                'intarray_col' => [
                    new ArrayExpression([1,-2,null,'42'], 'int4'),
                    [1,-2,null,42],
                ],
                'textarray2_col' => [
                    new ArrayExpression([['text'], [null], [1]], 'text[][]'),
                    [['text'], [null], ['1']],
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
                    [null, null],
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
                    [],
                ],
            ]],
            'arrays packed in classes' => [[
                'intarray_col' => [
                    new ArrayExpression([1,-2,null,'42'], 'int'),
                    [1,-2,null,42],
                ],
                'textarray2_col' => [
                    new ArrayExpression([['text'], [null], [1]], 'text[][]'),
                    [['text'], [null], ['1']],
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
                    [[',', 'null', true, 'false', 'f']],
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
    public function testArrayValues($properties): void
    {
        $this->reloadFixtureAfterTest();

        $type = new ArrayAndJsonTypes();

        foreach ($properties as $property => $expected) {
            $type->$property = $expected[0];
        }

        $type->save();

        $typeQuery = $type::query();

        $type = $typeQuery->one();

        foreach ($properties as $property => $expected) {
            $expected = $expected[1] ?? $expected[0];
            $value = $type->$property;

            if ($expected instanceof ArrayExpression) {
                $expected = $expected->getValue();
            }

            $this->assertSame($expected, $value, 'In column ' . $property);
        }

        /** Testing update */
        foreach ($properties as $property => $expected) {
            $type->markPropertyChanged($property);
        }

        $this->assertSame(1, $type->update(), 'The record got updated');
    }
}
