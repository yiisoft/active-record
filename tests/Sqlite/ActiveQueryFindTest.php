<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Sqlite;

use Yiisoft\ActiveRecord\Tests\ActiveQueryFindTest as AbstractActiveQueryFindTest;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * @group sqlite
 */
final class ActiveQueryFindTest extends AbstractActiveQueryFindTest
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
