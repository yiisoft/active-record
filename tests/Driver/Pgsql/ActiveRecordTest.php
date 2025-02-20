<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Pgsql;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ArArrayHelper;
use Yiisoft\ActiveRecord\Tests\Driver\Pgsql\Stubs\Item;
use Yiisoft\ActiveRecord\Tests\Driver\Pgsql\Stubs\Promotion;
use Yiisoft\ActiveRecord\Tests\Driver\Pgsql\Stubs\Type;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\ArrayAndJsonTypes;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Beta;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\BoolAR;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\DefaultPk;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\UserAR;
use Yiisoft\ActiveRecord\Tests\Support\PgsqlHelper;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Expression\ArrayExpression;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Expression\JsonExpression;
use Yiisoft\Db\Pgsql\Schema as SchemaPgsql;
use Yiisoft\Factory\Factory;

final class ActiveRecordTest extends \Yiisoft\ActiveRecord\Tests\ActiveRecordTest
{
    protected function createConnection(): ConnectionInterface
    {
        return (new PgsqlHelper())->createConnection();
    }

    protected function createFactory(): Factory
    {
        return (new PgsqlHelper())->createFactory($this->db());
    }

    public function testDefaultValues(): void
    {
        $this->checkFixture($this->db(), 'type');

        $arClass = new Type();

        $arClass->loadDefaultValues();

        $this->assertEquals(1, $arClass->int_col2);
        $this->assertEquals('something', $arClass->char_col2);
        $this->assertEquals(1.23, $arClass->float_col2);
        $this->assertEquals(33.22, $arClass->numeric_col);
        $this->assertTrue($arClass->bool_col2);
        $this->assertEquals('2002-01-01 00:00:00', $arClass->time);

        $arClass = new Type();
        $arClass->char_col2 = 'not something';

        $arClass->loadDefaultValues();
        $this->assertEquals('not something', $arClass->char_col2);

        $arClass = new Type();
        $arClass->char_col2 = 'not something';

        $arClass->loadDefaultValues(false);
        $this->assertEquals('something', $arClass->char_col2);
    }

    public function testCastValues(): void
    {
        $this->checkFixture($this->db(), 'type');

        $arClass = new Type();

        $arClass->int_col = 123;
        $arClass->int_col2 = 456;
        $arClass->smallint_col = 42;
        $arClass->char_col = '1337';
        $arClass->char_col2 = 'test';
        $arClass->char_col3 = 'test123';
        $arClass->float_col = 3.742;
        $arClass->float_col2 = 42.1337;
        $arClass->bool_col = true;
        $arClass->bool_col2 = false;
        $arClass->json_col = ['a' => 'b', 'c' => null, 'd' => [1, 2, 3]];

        $arClass->save();

        /** @var $model Type */
        $aqClass = new ActiveQuery(Type::class);
        $query = $aqClass->one();

        $this->assertSame(123, $query->int_col);
        $this->assertSame(456, $query->int_col2);
        $this->assertSame(42, $query->smallint_col);
        $this->assertSame('1337', trim($query->char_col));
        $this->assertSame('test', $query->char_col2);
        $this->assertSame('test123', $query->char_col3);
        $this->assertSame(3.742, $query->float_col);
        $this->assertSame(42.1337, $query->float_col2);
        $this->assertEquals(true, $query->bool_col);
        $this->assertEquals(false, $query->bool_col2);
        $this->assertSame(['a' => 'b', 'c' => null, 'd' => [1, 2, 3]], $query->json_col);
    }

    public function testExplicitPkOnAutoIncrement(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customer = new Customer();
        $customer->setId(1337);
        $customer->setEmail('user1337@example.com');
        $customer->setName('user1337');
        $customer->setAddress('address1337');
        $this->assertTrue($customer->getIsNewRecord());

        $customer->save();
        $this->assertEquals(1337, $customer->getId());
        $this->assertFalse($customer->getIsNewRecord());
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/15482}
     */
    public function testEagerLoadingUsingStringIdentifiers(): void
    {
        $this->checkFixture($this->db(), 'beta');

        $betaQuery = new ActiveQuery(Beta::class);
        $betas = $betaQuery->with('alpha')->all();
        $this->assertNotEmpty($betas);

        $alphaIdentifiers = [];

        /** @var Beta[] $betas */
        foreach ($betas as $beta) {
            $this->assertNotNull($beta->getAlpha());
            $this->assertEquals($beta->getAlphaStringIdentifier(), $beta->getAlpha()->getStringIdentifier());
            $alphaIdentifiers[] = $beta->getAlpha()->getStringIdentifier();
        }

        $this->assertEquals(['1', '01', '001', '001', '2', '2b', '2b', '02'], $alphaIdentifiers);
    }

    public function testBooleanProperty(): void
    {
        $this->checkFixture($this->db(), 'customer', true);

        $customer = new Customer();
        $customer->setName('boolean customer');
        $customer->setEmail('mail@example.com');
        $customer->setBoolStatus(false);
        $customer->save();

        $customer->refresh();
        $this->assertFalse($customer->getBoolStatus());

        $customer->setBoolStatus(true);

        $customer->save();
        $customer->refresh();
        $this->assertTrue($customer->getBoolStatus());

        $customerQuery = new ActiveQuery(Customer::class);
        $customers = $customerQuery->where(['bool_status' => true])->all();
        $this->assertCount(3, $customers);

        $customers = $customerQuery->where(['bool_status' => false])->all();
        $this->assertCount(1, $customers);
    }

    public function testBooleanValues(): void
    {
        $this->checkFixture($this->db(), 'bool_values');

        $command = $this->db()->createCommand();
        $command->insertBatch('bool_values', [[true], [false]], ['bool_col'])->execute();
        $boolARQuery = new ActiveQuery(BoolAR::class);

        $this->assertTrue($boolARQuery->where(['bool_col' => true])->one()->bool_col);
        $this->assertFalse($boolARQuery->where(['bool_col' => false])->one()->bool_col);

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
        $this->checkFixture($this->db(), 'bool_user');

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

        $userQuery = new ActiveQuery(UserAR::class);
        $this->assertCount(1, $userQuery->where(['is_deleted' => false])->all());
        $this->assertCount(0, $userQuery->where(['is_deleted' => true])->all());
        $this->assertCount(1, $userQuery->where(['is_deleted' => [true, false]])->all());
    }

    public function testBooleanDefaultValues(): void
    {
        $this->checkFixture($this->db(), 'bool_values');

        $arClass = new BoolAR();

        $this->assertNull($arClass->bool_col);
        $this->assertTrue($arClass->default_true);
        $this->assertFalse($arClass->default_false);

        $arClass->loadDefaultValues();

        $this->assertNull($arClass->bool_col);
        $this->assertTrue($arClass->default_true);
        $this->assertFalse($arClass->default_false);
        $this->assertTrue($arClass->save());
    }

    public function testPrimaryKeyAfterSave(): void
    {
        $this->checkFixture($this->db(), 'default_pk');

        $record = new DefaultPk();

        $record->type = 'type';

        $record->save();

        $this->assertEquals(5, $record->getPrimaryKey());
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
                    new ArrayExpression([1,-2,null,'42'], 'int[]'),
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
        $this->checkFixture($this->db(), 'array_and_json_types', true);

        $type = new ArrayAndJsonTypes();

        foreach ($properties as $property => $expected) {
            $type->set($property, $expected[0]);
        }

        $type->save();

        $typeQuery = new ActiveQuery($type::class);

        $type = $typeQuery->one();

        foreach ($properties as $property => $expected) {
            $expected = $expected[1] ?? $expected[0];
            $value = $type->get($property);

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

    public function testRelationViaArray(): void
    {
        $this->checkFixture($this->db(), 'promotion');

        $promotionQuery = new ActiveQuery(Promotion::class);
        /** @var Promotion[] $promotions */
        $promotions = $promotionQuery->with('itemsViaArray')->all();

        $this->assertSame([1, 2], ArArrayHelper::getColumn($promotions[0]->getItemsViaArray(), 'id'));
        $this->assertSame([3, 4, 5], ArArrayHelper::getColumn($promotions[1]->getItemsViaArray(), 'id'));
        $this->assertSame([1, 3], ArArrayHelper::getColumn($promotions[2]->getItemsViaArray(), 'id'));
        $this->assertCount(0, $promotions[3]->getItemsViaArray());

        /** Test inverse relation */
        foreach ($promotions as $promotion) {
            foreach ($promotion->getItemsViaArray() as $item) {
                $this->assertTrue($item->isRelationPopulated('promotionsViaArray'));
            }
        }

        $this->assertSame([1, 3], ArArrayHelper::getColumn($promotions[0]->getItemsViaArray()[0]->getPromotionsViaArray(), 'id'));
        $this->assertSame([1], ArArrayHelper::getColumn($promotions[0]->getItemsViaArray()[1]->getPromotionsViaArray(), 'id'));
        $this->assertSame([2, 3], ArArrayHelper::getColumn($promotions[1]->getItemsViaArray()[0]->getPromotionsViaArray(), 'id'));
        $this->assertSame([2], ArArrayHelper::getColumn($promotions[1]->getItemsViaArray()[1]->getPromotionsViaArray(), 'id'));
        $this->assertSame([2], ArArrayHelper::getColumn($promotions[1]->getItemsViaArray()[2]->getPromotionsViaArray(), 'id'));
        $this->assertSame([1, 3], ArArrayHelper::getColumn($promotions[2]->getItemsViaArray()[0]->getPromotionsViaArray(), 'id'));
        $this->assertSame([2, 3], ArArrayHelper::getColumn($promotions[2]->getItemsViaArray()[1]->getPromotionsViaArray(), 'id'));
    }

    public function testLazzyRelationViaArray(): void
    {
        $this->checkFixture($this->db(), 'item');

        $itemQuery = new ActiveQuery(Item::class);
        /** @var Item[] $items */
        $items = $itemQuery->all();

        $this->assertFalse($items[0]->isRelationPopulated('promotionsViaArray'));
        $this->assertFalse($items[1]->isRelationPopulated('promotionsViaArray'));
        $this->assertFalse($items[2]->isRelationPopulated('promotionsViaArray'));
        $this->assertFalse($items[3]->isRelationPopulated('promotionsViaArray'));
        $this->assertFalse($items[4]->isRelationPopulated('promotionsViaArray'));

        $this->assertSame([1, 3], ArArrayHelper::getColumn($items[0]->getPromotionsViaArray(), 'id'));
        $this->assertSame([1], ArArrayHelper::getColumn($items[1]->getPromotionsViaArray(), 'id'));
        $this->assertSame([2, 3], ArArrayHelper::getColumn($items[2]->getPromotionsViaArray(), 'id'));
        $this->assertSame([2], ArArrayHelper::getColumn($items[3]->getPromotionsViaArray(), 'id'));
        $this->assertSame([2], ArArrayHelper::getColumn($items[4]->getPromotionsViaArray(), 'id'));
    }
}
