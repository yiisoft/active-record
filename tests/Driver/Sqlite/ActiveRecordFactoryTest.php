<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Sqlite;

use Yiisoft\ActiveRecord\ConnectionProvider;
use Yiisoft\ActiveRecord\Tests\Support\SqliteHelper;

final class ActiveRecordFactoryTest extends \Yiisoft\ActiveRecord\Tests\ActiveRecordFactoryTest
{
    public function setUp(): void
    {
        parent::setUp();

        $sqliteHelper = new SqliteHelper();
        ConnectionProvider::set($sqliteHelper->createConnection());
        $this->arFactory = $sqliteHelper->createARFactory($this->db());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->db()->close();

        unset($this->arFactory);

        ConnectionProvider::unset();
    }
}
