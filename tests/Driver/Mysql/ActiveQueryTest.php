<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Mysql;

use Yiisoft\ActiveRecord\Tests\ActiveQueryTest as AbstractActiveQueryTest;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * @group mysql
 */
final class ActiveQueryTest extends AbstractActiveQueryTest
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
