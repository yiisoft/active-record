<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Sqlite;

use Yiisoft\ActiveRecord\Tests\Support\SqliteHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

final class ActiveRecordFactoryTest extends \Yiisoft\ActiveRecord\Tests\ActiveRecordFactoryTest
{
    protected function createConnection(): ConnectionInterface
    {
        return (new SqliteHelper())->createConnection();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $sqliteHelper = new SqliteHelper();
        $this->arFactory = $sqliteHelper->createARFactory($this->db());
    }

    protected function tearDown(): void
    {
        unset($this->arFactory);

        parent::tearDown();
    }
}
