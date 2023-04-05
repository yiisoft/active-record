<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Support;

use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Pgsql\PdoConnection;
use Yiisoft\Db\Pgsql\PdoDriver;

final class PgsqlHelper extends ConnectionHelper
{
    private string $dsn = 'pgsql:host=127.0.0.1;dbname=yiitest;port=5432';
    private string $username = 'root';
    private string $password = 'root';
    private string $charset = 'UTF8';

    public function createConnection(): ConnectionInterface
    {
        $pdoDriver = new PdoDriver($this->dsn, $this->username, $this->password);
        $pdoDriver->charset($this->charset);

        return new PdoConnection($pdoDriver, $this->createSchemaCache());
    }
}
