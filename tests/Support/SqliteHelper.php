<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Support;

use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\PdoConnection;
use Yiisoft\Db\Sqlite\PdoDriver;

final class SqliteHelper extends ConnectionHelper
{
    private string $charset = 'UTF8';

    public function createConnection(): ConnectionInterface
    {
        $pdoDriver = new PdoDriver('sqlite::memory:');
        $pdoDriver->charset($this->charset);

        return new PdoConnection($pdoDriver, $this->createSchemaCache());
    }
}
