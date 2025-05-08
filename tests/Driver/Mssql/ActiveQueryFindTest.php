<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Mssql;

use Yiisoft\ActiveRecord\Tests\Support\MssqlHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

final class ActiveQueryFindTest extends \Yiisoft\ActiveRecord\Tests\ActiveQueryFindTest
{
    protected static function createConnection(): ConnectionInterface
    {
        return (new MssqlHelper())->createConnection();
    }
}
