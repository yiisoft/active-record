<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Mysql;

use Yiisoft\ActiveRecord\Tests\ActiveQueryFindTest as AbstractActiveQueryFindTest;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * @group mysql
 */
final class ActiveQueryFindTest extends AbstractActiveQueryFindTest
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
