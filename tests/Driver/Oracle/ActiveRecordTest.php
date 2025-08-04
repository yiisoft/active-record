<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Oracle;

use PHPUnit\Framework\Attributes\TestWith;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Driver\Oracle\Stubs\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Type;
use Yiisoft\ActiveRecord\Tests\Support\OracleHelper;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Factory\Factory;

final class ActiveRecordTest extends \Yiisoft\ActiveRecord\Tests\ActiveRecordTest
{
    protected static function createConnection(): ConnectionInterface
    {
        return (new OracleHelper())->createConnection();
    }

    protected function createFactory(): Factory
    {
        return (new OracleHelper())->createFactory($this->db());
    }

    public function testDefaultValues(): void
    {
        $arClass = new Type();
        $arClass->loadDefaultValues();
        $this->assertSame(1, $arClass->int_col2);
        $this->assertSame('something', $arClass->char_col2);
        $this->assertSame(1.23, $arClass->float_col2);
        $this->assertSame(33.22, $arClass->numeric_col);
        $this->assertTrue($arClass->bool_col2);

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

        $customer->setName('boolean customer');
        $customer->setEmail('mail@example.com');
        $customer->setBoolStatus(true);

        $customer->save();
        $customer->refresh();
        $this->assertTrue($customer->getBoolStatus());

        $customer->setBoolStatus(false);
        $customer->save();

        $customer->refresh();
        $this->assertFalse($customer->getBoolStatus());

        $customerQuery = Customer::query();
        $customers = $customerQuery->where(['bool_status' => '1'])->all();
        $this->assertCount(2, $customers);

        $customerQuery = Customer::query();
        $customers = $customerQuery->where(['bool_status' => '0'])->all();
        $this->assertCount(2, $customers);
    }

    #[TestWith([[], [], [], []])]
    public function testUpsert(
        array $values,
        array|null $insertProperties,
        array|bool $updateProperties,
        array $expected,
        array|null $expectedAfterRefresh = null,
    ): void {
        $customer = new Customer();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Oracle\DMLQueryBuilder::upsertReturning() is not supported by Oracle.');

        $customer->upsert($insertProperties, $updateProperties);
    }
}
