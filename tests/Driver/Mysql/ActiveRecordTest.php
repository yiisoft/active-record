<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Mysql;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Driver\Mysql\Stubs\Type;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Beta;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Support\MysqlHelper;

final class ActiveRecordTest extends \Yiisoft\ActiveRecord\Tests\ActiveRecordTest
{
    public function setUp(): void
    {
        parent::setUp();

        $mysqlHelper = new MysqlHelper();
        $this->db = $mysqlHelper->createConnection();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->db->close();

        unset($this->db);
    }

    public function testCastValues(): void
    {
        $this->checkFixture($this->db, 'type');

        $arClass = new Type($this->db);

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

        $arClass->save();

        /** @var $model Type */
        $aqClass = new ActiveQuery(Type::class, $this->db);
        $query = $aqClass->onePopulate();

        $this->assertSame(123, $query->int_col);
        $this->assertSame(456, $query->int_col2);
        $this->assertSame(42, $query->smallint_col);
        $this->assertSame('1337', trim($query->char_col));
        $this->assertSame('test', $query->char_col2);
        $this->assertSame('test123', $query->char_col3);
        $this->assertSame('B', $query->enum_col);
    }

    public function testExplicitPkOnAutoIncrement(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customer = new Customer($this->db);

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
        $this->checkFixture($this->db, 'beta');

        $betaQuery = new ActiveQuery(Beta::class, $this->db);

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
}
