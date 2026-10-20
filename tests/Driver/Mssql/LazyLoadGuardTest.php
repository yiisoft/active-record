<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Mssql;

use Yiisoft\ActiveRecord\Tests\Support\MssqlHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

final class LazyLoadGuardTest extends \Yiisoft\ActiveRecord\Tests\LazyLoadGuardTest
{
    protected static function createConnection(): ConnectionInterface
    {
        return (new MssqlHelper())->createConnection();
    }
}
