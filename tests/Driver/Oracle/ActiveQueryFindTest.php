<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Oracle;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Driver\Oracle\Stubs\Customer as CustomerWithRownumid;
use Yiisoft\ActiveRecord\Tests\Support\OracleHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

final class ActiveQueryFindTest extends \Yiisoft\ActiveRecord\Tests\ActiveQueryFindTest
{
    protected static function createConnection(): ConnectionInterface
    {
        return (new OracleHelper())->createConnection();
    }

    public function testFindLimit(): void
    {
        /** one */
        $customerQuery = CustomerWithRownumid::query();
        $customer = $customerQuery->orderBy('id')->one();
        $this->assertEquals('user1', $customer->getName());

        /** all */
        $customerQuery = CustomerWithRownumid::query();
        $customers = $customerQuery->all();
        $this->assertCount(3, $customers);

        /** limit */
        $customerQuery = CustomerWithRownumid::query();
        $customers = $customerQuery->orderBy('id')->limit(1)->all();
        $this->assertCount(1, $customers);
        $this->assertEquals('user1', $customers[0]->getName());

        $customers = $customerQuery->orderBy('id')->limit(1)->offset(1)->all();
        $this->assertCount(1, $customers);
        $this->assertEquals('user2', $customers[0]->getName());

        $customers = $customerQuery->orderBy('id')->limit(1)->offset(2)->all();
        $this->assertCount(1, $customers);
        $this->assertEquals('user3', $customers[0]->getName());

        $customers = $customerQuery->orderBy('id')->limit(2)->offset(1)->all();
        $this->assertCount(2, $customers);
        $this->assertEquals('user2', $customers[0]->getName());
        $this->assertEquals('user3', $customers[1]->getName());

        $customers = $customerQuery->limit(2)->offset(3)->all();
        $this->assertCount(0, $customers);

        /** offset */
        $customerQuery = CustomerWithRownumid::query();
        $customer = $customerQuery->orderBy('id')->offset(0)->one();
        $this->assertEquals('user1', $customer->getName());

        $customer = $customerQuery->orderBy('id')->offset(1)->one();
        $this->assertEquals('user2', $customer->getName());

        $customer = $customerQuery->orderBy('id')->offset(2)->one();
        $this->assertEquals('user3', $customer->getName());

        $customer = $customerQuery->offset(3)->one();
        $this->assertNull($customer);
    }
}
