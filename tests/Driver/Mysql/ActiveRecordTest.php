<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Mysql;

use Yiisoft\ActiveRecord\Tests\Driver\Mysql\Stubs\Type;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Beta;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Support\MysqlHelper;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Factory\Factory;

final class ActiveRecordTest extends \Yiisoft\ActiveRecord\Tests\ActiveRecordTest
{
    public function testCastValues(): void
    {
        $this->reloadFixtureAfterTest();

        $arClass = new Type();

        $arClass->int_col = 123;
        $arClass->int_col2 = 456;
        $arClass->smallint_col = 42;
        $arClass->char_col = '1337';
        $arClass->char_col2 = 'test';
        $arClass->char_col3 = 'test123';
        $arClass->enum_col = 'B';
        $arClass->float_col = 3.742;
        $arClass->float_col2 = 42.1337;
        $arClass->bool_col = true;
        $arClass->bool_col2 = false;
        $arClass->json_col = ['a' => 'b', 'c' => null, 'd' => [1, 2, 3]];

        $arClass->save();

        /** @var $model Type */
        $aqClass = Type::query();
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
        $this->assertSame('B', $query->enum_col);
        $this->assertSame(['a' => 'b', 'c' => null, 'd' => [1, 2, 3]], $query->json_col);
    }

    public function testExplicitPkOnAutoIncrement(): void
    {
        $this->reloadFixtureAfterTest();

        $customer = new Customer();

        $customer->setId(1337);
        $customer->setEmail('user1337@example.com');
        $customer->setName('user1337');
        $customer->setAddress('address1337');

        $this->assertTrue($customer->isNew());

        $customer->save();

        $this->assertEquals(1337, $customer->getId());
        $this->assertFalse($customer->isNew());
    }

    /**
     * @see https://github.com/yiisoft/yii2/issues/15482
     */
    public function testEagerLoadingUsingStringIdentifiers(): void
    {
        $betaQuery = Beta::query();

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
    protected static function createConnection(): ConnectionInterface
    {
        return (new MysqlHelper())->createConnection();
    }

    protected function createFactory(): Factory
    {
        return (new MysqlHelper())->createFactory($this->db());
    }
}
