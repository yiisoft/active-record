<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Support;

use PDO;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Oracle\Connection;
use Yiisoft\Db\Oracle\Driver;

use function getenv;

final class OracleHelper extends ConnectionHelper
{
    public function createConnection(): ConnectionInterface
    {
        $database = getenv('YII_ORACLE_DATABASE');
        $host = getenv('YII_ORACLE_HOST');
        $port = getenv('YII_ORACLE_PORT');
        $user = getenv('YII_ORACLE_USER');
        $password = getenv('YII_ORACLE_PASSWORD');

        $pdoDriver = new Driver("oci:dbname=//$host:$port/$database", $user, $password);
        $pdoDriver->charset('AL32UTF8');
        $pdoDriver->attributes([PDO::ATTR_STRINGIFY_FETCHES => true]);

        return new Connection($pdoDriver, $this->createSchemaCache());
    }
}
