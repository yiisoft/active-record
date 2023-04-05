<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Support;

use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Mssql\PdoConnection;
use Yiisoft\Db\Mssql\PdoDriver;

final class MssqlHelper extends ConnectionHelper
{
    private string $dsn = 'sqlsrv:Server=127.0.0.1,1433;Database=yiitest';
    private string $username = 'SA';
    private string $password = 'YourStrong!Passw0rd';
    private string $charset = 'UTF8';

    public function createConnection(): ConnectionInterface
    {
        $pdoDriver = new PdoDriver($this->dsn, $this->username, $this->password);
        $pdoDriver->charset($this->charset);

        return new PdoConnection($pdoDriver, $this->createSchemaCache());
    }
}
