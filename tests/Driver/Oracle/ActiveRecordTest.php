<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Oracle;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Driver\Oracle\Stubs\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Type;
use Yiisoft\ActiveRecord\Tests\Support\OracleHelper;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Factory\Factory;

final class ActiveRecordTest extends \Yiisoft\ActiveRecord\Tests\ActiveRecordTest
{
    protected function createConnection(): ConnectionInterface
    {
        return (new OracleHelper())->createConnection();
    }

    protected function createFactory(): Factory
    {
        return (new OracleHelper())->createFactory($this->db());
    }

    public function testCastValues(): void
    {
        $this->markTestSkipped('Cant bind floats without support from a custom PDO driver.');

        $this->checkFixture($this->db(), 'customer');

        $arClass = new Type();
        $arClass->int_col = 123;
        $arClass->int_col2 = 456;
        $arClass->smallint_col = 42;
        $arClass->char_col = '1337';
        $arClass->char_col2 = 'test';
        $arClass->char_col3 = 'test123';
        /** can't bind floats without support from a custom PDO driver */
        $arClass->float_col = 2;
        $arClass->float_col2 = 1;
        $arClass->bool_col = 1;
        $arClass->bool_col2 = 0;
        $arClass->save();

        $aqClass = new ActiveQuery(Type::class);
        $query = $aqClass->one();

        $this->assertSame(123, $query->int_col);
        $this->assertSame(456, $query->int_col2);
        $this->assertSame(42, $query->smallint_col);
        $this->assertSame('1337', trim($query->char_col));
        $this->assertSame('test', $query->char_col2);
        $this->assertSame('test123', $query->char_col3);
        $this->assertSame(2.0, $query->float_col);
        $this->assertSame(1.0, $query->float_col2);
        $this->assertEquals('1', $query->bool_col);
        $this->assertEquals('0', $query->bool_col2);
    }

    public function testDefaultValues(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $arClass = new Type();
        $arClass->loadDefaultValues();
        $this->assertEquals(1, $arClass->int_col2);
        $this->assertEquals('something', $arClass->char_col2);
        $this->assertEquals(1.23, $arClass->float_col2);
        $this->assertEquals(33.22, $arClass->numeric_col);
        $this->assertEquals('1', $arClass->bool_col2);

        // not testing $arClass->time, because oci\Schema can't read default value

        $arClass = new Type();
        $arClass->char_col2 = 'not something';

        $arClass->loadDefaultValues();
        $this->assertEquals('not something', $arClass->char_col2);

        $arClass = new Type();
        $arClass->char_col2 = 'not something';

        $arClass->loadDefaultValues(false);
        $this->assertEquals('something', $arClass->char_col2);
    }

    /**
     * Some PDO implementations (e.g. cubrid) do not support boolean values.
     *
     * Make sure this does not affect AR layer.
     */
    public function testBooleanProperty(): void
    {
        $this->checkFixture($this->db(), 'customer', true);

        $customer = new Customer();

        $customer->setName('boolean customer');
        $customer->setEmail('mail@example.com');
        $customer->setStatus(1);

        $customer->save();
        $customer->refresh();
        $this->assertEquals(1, $customer->getStatus());

        $customer->setStatus(0);
        $customer->save();

        $customer->refresh();
        $this->assertEquals(0, $customer->getStatus());

        $customerQuery = new ActiveQuery(Customer::class);
        $customers = $customerQuery->where(['status' => 1])->all();
        $this->assertCount(2, $customers);

        $customerQuery = new ActiveQuery(Customer::class);
        $customers = $customerQuery->where(['status' => '0'])->all();
        $this->assertCount(1, $customers);
    }
}
