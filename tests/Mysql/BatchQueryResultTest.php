<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Mysql;

use Yiisoft\ActiveRecord\Tests\BatchQueryResultTest as AbstractBatchQueryResultTest;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * @group mysql
 */
final class BatchQueryResultTest extends AbstractBatchQueryResultTest
{
    protected string $driverName = 'mysql';
    protected ConnectionInterface $db;

    public function setUp(): void
    {
        parent::setUp();

        $this->db = $this->mysqlConnection;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->mysqlConnection->close();

        unset($this->mysqlConnection);
    }
}
