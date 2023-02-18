<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Pgsql;

use Yiisoft\ActiveRecord\Tests\BatchQueryResultTest as AbstractBatchQueryResultTest;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * @group pgsql
 */
final class BatchQueryResultTest extends AbstractBatchQueryResultTest
{
    protected string $driverName = 'pgsql';
    protected ConnectionInterface $db;

    public function setUp(): void
    {
        parent::setUp();

        $this->db = $this->pgsqlConnection;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->pgsqlConnection->close();

        unset($this->pgsqlConnection);
    }
}
