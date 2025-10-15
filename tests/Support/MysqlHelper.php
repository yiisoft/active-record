<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Support;

use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Mysql\Connection;
use Yiisoft\Db\Mysql\Driver;

use function getenv;

final class MysqlHelper extends ConnectionHelper
{
    public function createConnection(): ConnectionInterface
    {
        $database = getenv('YII_MYSQL_DATABASE');
        $host = getenv('YII_MYSQL_HOST');
        $port = getenv('YII_MYSQL_PORT');
        $user = getenv('YII_MYSQL_USER');
        $password = getenv('YII_MYSQL_PASSWORD');

        $pdoDriver = new Driver("mysql:host=$host;dbname=$database;port=$port", $user, $password);
        $pdoDriver->charset('UTF8MB4');

        return new Connection($pdoDriver, $this->createSchemaCache());
    }
}
