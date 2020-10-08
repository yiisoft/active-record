<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\Db\Query\BatchQueryResult;

abstract class BatchQueryResultTest extends TestCase
{
    public function testQuery(): void
    {
        $this->checkFixture($this->db, 'customer', true);

        $customerQuery = new ActiveQuery(Customer::class, $this->db);

        $query = $customerQuery->orderBy('id');

        $result = $query->batch(2);

        $this->assertInstanceOf(BatchQueryResult::class, $result);
        $this->assertEquals(2, $result->getBatchSize());
        $this->assertSame($result->getQuery(), $query);

        /** normal query */
        $customerQuery = new ActiveQuery(Customer::class, $this->db);

        $query = $customerQuery->orderBy('id');

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
        $query = $customerQuery->where(['id' => 100]);

        $allRows = [];

        $batch = $query->batch(2);

        foreach ($batch as $rows) {
            $allRows = array_merge($allRows, $rows);
        }

        $this->assertCount(0, $allRows);

        /** query with index */
        $customerQuery = new ActiveQuery(Customer::class, $this->db);

        $query = $customerQuery->indexBy('name');

        $allRows = [];

        foreach ($query->batch(2) as $rows) {
            $allRows = array_merge($allRows, $rows);
        }

        $this->assertCount(3, $allRows);
        $this->assertEquals('address1', $allRows['user1']['address']);
        $this->assertEquals('address2', $allRows['user2']['address']);
        $this->assertEquals('address3', $allRows['user3']['address']);

        /** each */
        $customerQuery = new ActiveQuery(Customer::class, $this->db);

        $query = $customerQuery->orderBy('id');

        $allRows = [];

        foreach ($query->each(2) as $index => $row) {
            $allRows[$index] = $row;
        }
        $this->assertCount(3, $allRows);
        $this->assertEquals('user1', $allRows[0]['name']);
        $this->assertEquals('user2', $allRows[1]['name']);
        $this->assertEquals('user3', $allRows[2]['name']);

        /** each with key */
        $customerQuery = new ActiveQuery(Customer::class, $this->db);

        $query = $customerQuery->orderBy('id')->indexBy('name');

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
        $this->checkFixture($this->db, 'customer');

        /** batch with eager loading */
        $customerQuery = new ActiveQuery(Customer::class, $this->db);

        $query = $customerQuery->with('orders')->orderBy('id');

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
        $this->checkFixture($this->db, 'customer');

        $customerQuery = new ActiveQuery(Customer::class, $this->db);

        $query = $customerQuery->orderBy('id')->limit(3)->indexBy('id');

        $customers = $this->getAllRowsFromBatch($query->batch(2));

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
