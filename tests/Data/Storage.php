<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Data;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\Db\Connectors\ConnectionPool;
use Yiisoft\Db\Contracts\ConnectionInterface;

/**
 * Class Storage
 *
 * @property int $id
 * @property array $data
 */
class Storage extends ActiveRecord
{
    public static function tableName()
    {
        return 'storage';
    }

    public static function getConnection(): ConnectionInterface
    {
        return ConnectionPool::getConnectionPool('mysql');
    }
}
