<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Sqlite;

use Yiisoft\ActiveRecord\Tests\ActiveQueryTest as BaseActiveQueryTest;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * @group sqlite
 */
final class ActiveQueryTest extends BaseActiveQueryTest
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
