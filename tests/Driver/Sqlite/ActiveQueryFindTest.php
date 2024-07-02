<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Sqlite;

use Yiisoft\ActiveRecord\Tests\Support\SqliteHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

final class ActiveQueryFindTest extends \Yiisoft\ActiveRecord\Tests\ActiveQueryFindTest
{
    protected function createConnection(): ConnectionInterface
    {
        return (new SqliteHelper())->createConnection();
    }
}
