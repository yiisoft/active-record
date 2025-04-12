<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Support;

use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Mssql\Connection;
use Yiisoft\Db\Mssql\Driver;

final class MssqlHelper extends ConnectionHelper
{
    private string $dsn = 'sqlsrv:Server=127.0.0.1,1433;Database=yiitest;Encrypt=no';
    private string $username = 'SA';
    private string $password = 'YourStrong!Passw0rd';
    private string $charset = 'UTF8';

    public function createConnection(): ConnectionInterface
    {
        $pdoDriver = new Driver($this->dsn, $this->username, $this->password);
        $pdoDriver->charset($this->charset);

        return new Connection($pdoDriver, $this->createSchemaCache());
    }
}
