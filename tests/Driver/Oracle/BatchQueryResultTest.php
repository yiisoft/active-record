<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Oracle;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Driver\Oracle\Stubs\Customer;
use Yiisoft\ActiveRecord\Tests\Support\OracleHelper;

final class BatchQueryResultTest extends \Yiisoft\ActiveRecord\Tests\BatchQueryResultTest
{
    public function setUp(): void
    {
        parent::setUp();

        $oracleHelper = new OracleHelper();
        $this->db = $oracleHelper->createConnection();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->db->close();

        unset($this->db);
    }

    public function testBatchWithIndexBy(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customerQuery = new ActiveQuery(Customer::class, $this->db);

        $query = $customerQuery->orderBy('id')->limit(3)->indexBy('id');

        $customers = $this->getAllRowsFromBatch($query->batch(2));

        $this->assertCount(3, $customers);
        $this->assertEquals('user1', $customers[0]->getName());
        $this->assertEquals('user2', $customers[1]->getName());
        $this->assertEquals('user3', $customers[2]->getName());
    }
}
