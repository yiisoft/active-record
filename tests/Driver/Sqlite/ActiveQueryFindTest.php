<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Sqlite;

use Yiisoft\ActiveRecord\ConnectionProvider;
use Yiisoft\ActiveRecord\Tests\Support\SqliteHelper;

final class ActiveQueryFindTest extends \Yiisoft\ActiveRecord\Tests\ActiveQueryFindTest
{
    public function setUp(): void
    {
        parent::setUp();

        $sqliteHelper = new SqliteHelper();
        ConnectionProvider::set($sqliteHelper->createConnection());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->db()->close();

        ConnectionProvider::unset();
    }
}
