<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Mysql;

use Yiisoft\ActiveRecord\Tests\Support\MysqlHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

final class BatchQueryResultTest extends \Yiisoft\ActiveRecord\Tests\BatchQueryResultTest
{
    protected function createConnection(): ConnectionInterface
    {
        return (new MysqlHelper())->createConnection();
    }
}
