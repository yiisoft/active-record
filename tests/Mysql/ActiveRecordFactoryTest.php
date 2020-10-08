<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Mysql;

use Yiisoft\ActiveRecord\Tests\ActiveRecordFactoryTest as AbstractActiveRecordFactoryTest;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;

/**
 * @group mysql
 */
final class ActiveRecordFactoryTest extends AbstractActiveRecordFactoryTest
{
    protected string $driverName = 'mysql';

    public function setUp(): void
    {
        parent::setUp();

        $this->arFactory->withConnection($this->mysqlConnection);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->mysqlConnection->close();

        unset($this->arFactory, $this->mysqlConnection);
    }

    public function testExplicitPkOnAutoIncrement(): void
    {
        $customer = $this->arFactory->createAR(Customer::class);

        $customer->id = 1337;
        $customer->email = 'user1337@example.com';
        $customer->name = 'user1337';
        $customer->address = 'address1337';

        $this->assertTrue($customer->isNewRecord);

        $customer->save();

        $this->assertEquals(1337, $customer->id);
        $this->assertFalse($customer->isNewRecord);
    }
}
