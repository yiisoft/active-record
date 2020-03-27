<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\Db\Query\BatchQueryResult;
use Yiisoft\Db\Query\Query;

abstract class BatchQueryResultTest extends TestCase
{
    protected ?string $driverName = 'mysql';

    public function testQuery(): void
    {
        $db = Customer::getConnection();

        $this->loadFixture($db);

        /** initialize property test */
        $query = new Query($db);

        $query->from('customer')->orderBy('id');

        $result = $query->batch(2);

        $this->assertInstanceOf(BatchQueryResult::class, $result);
        $this->assertEquals(2, $result->getBatchSize());
        $this->assertSame($result->getQuery(), $query);

        /** normal query */
        $query = new Query($db);

        $query->from('customer')->orderBy('id');

        $allRows = [];

        $batch = $query->batch(2);

        foreach ($batch as $rows) {
            $allRows = array_merge($allRows, $rows);
        }

        $this->assertCount(3, $allRows);
        $this->assertEquals('user1', $allRows[0]['name']);
        $this->assertEquals('user2', $allRows[1]['name']);
        $this->assertEquals('user3', $allRows[2]['name']);

        /** rewind */
        $allRows = [];

        foreach ($batch as $rows) {
            $allRows = array_merge($allRows, $rows);
        }

        $this->assertCount(3, $allRows);

        /** reset */
        $batch->reset();

        /** empty query */
        $query = new Query($db);

        $query->from('customer')->where(['id' => 100]);

        $allRows = [];

        $batch = $query->batch(2);

        foreach ($batch as $rows) {
            $allRows = array_merge($allRows, $rows);
        }

        $this->assertCount(0, $allRows);

        /** query with index */
        $query = new Query($db);

        $query->from('customer')->indexBy('name');

        $allRows = [];

        foreach ($query->batch(2) as $rows) {
            $allRows = array_merge($allRows, $rows);
        }

        $this->assertCount(3, $allRows);
        $this->assertEquals('address1', $allRows['user1']['address']);
        $this->assertEquals('address2', $allRows['user2']['address']);
        $this->assertEquals('address3', $allRows['user3']['address']);

        /** each */
        $query = new Query($db);

        $query->from('customer')->orderBy('id');
        $allRows = [];

        foreach ($query->each(2) as $index => $row) {
            $allRows[$index] = $row;
        }
        $this->assertCount(3, $allRows);
        $this->assertEquals('user1', $allRows[0]['name']);
        $this->assertEquals('user2', $allRows[1]['name']);
        $this->assertEquals('user3', $allRows[2]['name']);

        /** each with key */
        $query = new Query($db);

        $query->from('customer')->orderBy('id')->indexBy('name');

        $allRows = [];

        foreach ($query->each(100) as $key => $row) {
            $allRows[$key] = $row;
        }

        $this->assertCount(3, $allRows);
        $this->assertEquals('address1', $allRows['user1']['address']);
        $this->assertEquals('address2', $allRows['user2']['address']);
        $this->assertEquals('address3', $allRows['user3']['address']);
    }

    public function testActiveQuery(): void
    {
        $db = Customer::getConnection();

        /** batch with eager loading */
        $query = (new Customer($db))->find()->with('orders')->orderBy('id');

        $customers = $this->getAllRowsFromBatch($query->batch(2));

        foreach ($customers as $customer) {
            $this->assertTrue($customer->isRelationPopulated('orders'));
        }

        $this->assertCount(3, $customers);
        $this->assertCount(1, $customers[0]->orders);
        $this->assertCount(2, $customers[1]->orders);
        $this->assertCount(0, $customers[2]->orders);
    }

    public function testBatchWithIndexBy(): void
    {
        $db = Customer::getConnection();

        $query = (new Customer($db))->find()->orderBy('id')->limit(3)->indexBy('id');

        $customers = $this->getAllRowsFromBatch($query->batch(2), $db);

        $this->assertCount(3, $customers);
        $this->assertEquals('user1', $customers[0]->name);
        $this->assertEquals('user2', $customers[1]->name);
        $this->assertEquals('user3', $customers[2]->name);
    }

    protected function getAllRowsFromBatch(BatchQueryResult $batch): array
    {
        $allRows = [];

        foreach ($batch as $rows) {
            $allRows = array_merge($allRows, $rows);
        }

        return $allRows;
    }

    protected function getAllRowsFromEach(BatchQueryResult $each): array
    {
        $allRows = [];

        foreach ($each as $index => $row) {
            $allRows[$index] = $row;
        }

        return $allRows;
    }
}
