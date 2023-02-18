<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Sqlite;

use Yiisoft\ActiveRecord\Tests\BatchQueryResultTest as AbstractBatchQueryResultTest;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * @group sqlite
 */
final class BatchQueryResultTest extends AbstractBatchQueryResultTest
{
    protected string $driverName = 'sqlite';
    protected ConnectionInterface $db;

    public function setUp(): void
    {
        parent::setUp();

        $this->db = $this->sqliteConnection;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->sqliteConnection->close();

        unset($this->sqliteConnection);
    }
}
