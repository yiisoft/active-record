<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Sqlite;

use Yiisoft\ActiveRecord\Tests\Support\SqliteHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

final class ConnectionProviderTest extends \Yiisoft\ActiveRecord\Tests\ConnectionProviderTest
{
    protected static function createConnection(): ConnectionInterface
    {
        return (new SqliteHelper())->createConnection();
    }
}
