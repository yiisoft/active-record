<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Oracle;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Driver\Oracle\Stubs\MagicCustomer as Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\Type;
use Yiisoft\ActiveRecord\Tests\Support\OracleHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

final class MagicActiveRecordTest extends \Yiisoft\ActiveRecord\Tests\MagicActiveRecordTest
{
    protected function createConnection(): ConnectionInterface
    {
        return (new OracleHelper())->createConnection();
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

        $customer->name = 'boolean customer';
        $customer->email = 'mail@example.com';
        $customer->status = '1';

        $customer->save();
        $customer->refresh();
        $this->assertEquals('1', $customer->status);

        $customer->status = '0';
        $customer->save();

        $customer->refresh();
        $this->assertEquals('0', $customer->status);

        $customerQuery = new ActiveQuery(Customer::class);
        $customers = $customerQuery->where(['status' => '1'])->all();
        $this->assertCount(2, $customers);

        $customerQuery = new ActiveQuery(Customer::class);
        $customers = $customerQuery->where(['status' => '0'])->all();
        $this->assertCount(1, $customers);
    }
}
