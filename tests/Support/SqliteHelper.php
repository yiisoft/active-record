<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Support;

use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\ConnectionPDO;
use Yiisoft\Db\Sqlite\PDODriver;

use function dirname;

final class SqliteHelper extends ConnectionHelper
{
    private string $charset = 'UTF8';

    public function createConnection(): ConnectionInterface
    {
        $pdoDriver = new PDODriver('sqlite::memory:');
        $pdoDriver->charset($this->charset);

        return new ConnectionPDO($pdoDriver, $this->createSchemaCache());
    }
}
