<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs;

use Yiisoft\ActiveRecord\ActiveRecord as BaseActiveRecord;
use Yiisoft\Db\Drivers\Connection;
use Yiisoft\Db\Drivers\ConnectionPool;

class ActiveRecord extends BaseActiveRecord
{
    private static ?string $driverName = null;

    public static function getConnection(): Connection
    {
        return ConnectionPool::getConnectionPool(self::$driverName);
    }

    /**
     * @param string|null $driverName
     */
    public static function setDriverName(?string $driverName): void
    {
        self::$driverName = $driverName;
    }
}
