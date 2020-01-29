<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Data;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\Db\Connectors\ConnectionPool;
use Yiisoft\Db\Contracts\ConnectionInterface;

/**
 * Class NullValues.
 *
 * @property int $id
 * @property int $var1
 * @property int $var2
 * @property int $var3
 * @property string $stringcol
 */
class NullValues extends ActiveRecord
{
    public static function tableName()
    {
        return 'null_values';
    }

    public static function getConnection(): ConnectionInterface
    {
        return ConnectionPool::getConnectionPool('mysql');
    }
}
