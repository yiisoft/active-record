<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Support;

use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Mysql\Connection;
use Yiisoft\Db\Mysql\Driver;

final class MysqlHelper extends ConnectionHelper
{
    private string $dsn = 'mysql:host=127.0.0.1;dbname=yiitest;port=3306';
    private string $username = 'root';
    private string $password = '';
    private string $charset = 'UTF8MB4';

    public function createConnection(): ConnectionInterface
    {
        $pdoDriver = new Driver($this->dsn, $this->username, $this->password);
        $pdoDriver->charset($this->charset);

        return new Connection($pdoDriver, $this->createSchemaCache());
    }
}
