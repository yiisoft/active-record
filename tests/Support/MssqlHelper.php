<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Support;

use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Mssql\Connection;
use Yiisoft\Db\Mssql\Driver;

final class MssqlHelper extends ConnectionHelper
{
    public function createConnection(): ConnectionInterface
    {
        $database = getenv('YII_MSSQL_DATABASE');
        $host = getenv('YII_MSSQL_HOST');
        $port = getenv('YII_MSSQL_PORT');
        $user = getenv('YII_MSSQL_USER');
        $password = getenv('YII_MSSQL_PASSWORD');

        $pdoDriver = new Driver(
            "sqlsrv:Server=$host,$port;Database=$database;TrustServerCertificate=true",
            $user,
            $password,
        );
        $pdoDriver->charset('UTF8');

        return new Connection($pdoDriver, $this->createSchemaCache());
    }
}
