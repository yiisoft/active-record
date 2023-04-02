<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Support;

use PDO;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Oracle\ConnectionPDO;
use Yiisoft\Db\Oracle\PDODriver;

final class OracleHelper extends ConnectionHelper
{
    private string $dsn = 'oci:dbname=localhost/XE;';
    private string $username = 'system';
    private string $password = 'root';
    private string $charset = 'AL32UTF8';

    public function createConnection(): ConnectionInterface
    {
        $pdoDriver = new PDODriver($this->dsn, $this->username, $this->password);
        $pdoDriver->charset($this->charset);
        $pdoDriver->attributes([PDO::ATTR_STRINGIFY_FETCHES => true]);

        return new ConnectionPDO($pdoDriver, $this->createSchemaCache());
    }
}
