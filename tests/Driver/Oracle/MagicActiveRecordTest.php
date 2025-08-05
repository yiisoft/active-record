<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Oracle;

use Yiisoft\ActiveRecord\Tests\Driver\Oracle\Stubs\MagicCustomer as Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\Type;
use Yiisoft\ActiveRecord\Tests\Support\OracleHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

final class MagicActiveRecordTest extends \Yiisoft\ActiveRecord\Tests\MagicActiveRecordTest
{
    protected static function createConnection(): ConnectionInterface
    {
        return (new OracleHelper())->createConnection();
    }

    public function testDefaultValues(): void
    {
        $arClass = new Type();
        $arClass->loadDefaultValues();
        $this->assertSame(1, $arClass->int_col2);
        $this->assertSame('something', $arClass->char_col2);
        $this->assertSame(1.23, $arClass->float_col2);
        $this->assertSame(33.22, $arClass->numeric_col);
        $this->assertSame(true, $arClass->bool_col2);

        // not testing $arClass->time, because oci\Schema can't read default value

        $arClass = new Type();
        $arClass->char_col2 = 'not something';

        $arClass->loadDefaultValues();
        $this->assertSame('not something', $arClass->char_col2);

        $arClass = new Type();
        $arClass->char_col2 = 'not something';

        $arClass->loadDefaultValues(false);
        $this->assertSame('something', $arClass->char_col2);
    }

    /**
     * Some PDO implementations (e.g. cubrid) do not support boolean values.
     *
     * Make sure this does not affect AR layer.
     */
    public function testBooleanProperty(): void
    {
        $this->reloadFixtureAfterTest();

        $customer = new Customer();

        $customer->name = 'boolean customer';
        $customer->email = 'mail@example.com';
        $customer->bool_status = true;

        $customer->save();
        $customer->refresh();
        $this->assertTrue($customer->bool_status);

        $customer->bool_status = false;
        $customer->save();

        $customer->refresh();
        $this->assertFalse($customer->bool_status);

        $customerQuery = Customer::query();
        $customers = $customerQuery->where(['bool_status' => '1'])->all();
        $this->assertCount(2, $customers);

        $customerQuery = Customer::query();
        $customers = $customerQuery->where(['bool_status' => '0'])->all();
        $this->assertCount(2, $customers);
    }
}
