<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Support;

use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;

final class SqliteHelper extends ConnectionHelper
{
    public function createConnection(): ConnectionInterface
    {
        $pdoDriver = new Driver('sqlite::memory:');
        $pdoDriver->charset('UTF8');

        return new Connection($pdoDriver, $this->createSchemaCache());
    }
}
