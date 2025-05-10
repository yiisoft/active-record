<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Mysql;

use Yiisoft\ActiveRecord\Tests\Support\MysqlHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

final class ActiveQueryTest extends \Yiisoft\ActiveRecord\Tests\ActiveQueryTest
{
    protected static function createConnection(): ConnectionInterface
    {
        return (new MysqlHelper())->createConnection();
    }
}
