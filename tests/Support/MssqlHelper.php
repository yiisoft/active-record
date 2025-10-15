<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Support;

use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Mssql\Connection;
use Yiisoft\Db\Mssql\Driver;

use function getenv;

final class MssqlHelper extends ConnectionHelper
{
    public function createConnection(): ConnectionInterface
    {
        $database = getenv('YII_MSSQL_DATABASE') ?: 'ar-test';
        $host = getenv('YII_MSSQL_HOST') ?: '127.0.0.1';
        $port = getenv('YII_MSSQL_PORT') ?: '1433';
        $user = getenv('YII_MSSQL_USER') ?: 'SA';
        $password = getenv('YII_MSSQL_PASSWORD') ?: 'YourStrong!Passw0rd';

        $pdoDriver = new Driver(
            "sqlsrv:Server=$host,$port;Database=$database;TrustServerCertificate=true",
            $user,
            $password,
        );
        $pdoDriver->charset('UTF8');

        return new Connection($pdoDriver, $this->createSchemaCache());
    }
}
