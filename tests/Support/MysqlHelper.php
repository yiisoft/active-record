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
        $database = getenv('YII_MYSQL_DATABASE') ?: 'ar-test';
        $host = getenv('YII_MYSQL_HOST') ?: '127.0.0.1';
        $port = getenv('YII_MYSQL_PORT') ?: '3306';
        $user = getenv('YII_MYSQL_USER') ?: 'yii';
        $password = getenv('YII_MYSQL_PASSWORD') ?: 'q1w2e3r4';

        $pdoDriver = new Driver("mysql:host=$host;dbname=$database;port=$port", $user, $password);
        $pdoDriver->charset('UTF8MB4');

        return new Connection($pdoDriver, $this->createSchemaCache());
    }
}
