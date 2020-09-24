<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Mssql;

use Yiisoft\ActiveRecord\Tests\BatchQueryResultTest as AbstractBatchQueryResultTest;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * @group mssql
 */
final class BatchQueryResultTest extends AbstractBatchQueryResultTest
{
    protected ?string $driverName = 'mssql';
    protected ConnectionInterface $db;

    public function setUp(): void
    {
        parent::setUp();

        $this->db = $this->mssqlConnection;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->mssqlConnection->close();

        unset($this->mssqlConnection);
    }
}
