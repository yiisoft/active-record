<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Oracle;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Driver\Oracle\Stubs\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerClosureField;
use Yiisoft\ActiveRecord\Tests\Support\OracleHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

final class ArrayableTraitTest extends \Yiisoft\ActiveRecord\Tests\ArrayableTraitTest
{
    protected function createConnection(): ConnectionInterface
    {
        return (new OracleHelper())->createConnection();
    }

    public function testToArray(): void
    {
        $this->checkFixture($this->db(), 'customer', true);

        $customerQuery = new ActiveQuery(Customer::class);
        $customer = $customerQuery->findOne(1);

        $this->assertSame(
            [
                'id' => 1,
                'email' => 'user1@example.com',
                'name' => 'user1',
                'address' => 'address1',
                'status' => 1,
                'bool_status' => '1',
                'profile_id' => 1,
            ],
            $customer->toArray(),
        );
    }

    public function testToArrayWithClosure(): void
    {
        $this->checkFixture($this->db(), 'customer', true);

        $customerQuery = new ActiveQuery(CustomerClosureField::class);
        $customer = $customerQuery->findOne(1);

        $this->assertSame(
            [
                'id' => 1,
                'email' => 'user1@example.com',
                'name' => 'user1',
                'address' => 'address1',
                'status' => 'active',
                'bool_status' => '1',
                'profile_id' => 1,
            ],
            $customer->toArray(),
        );
    }
}
