<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Data;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\Db\Connectors\ConnectionPool;
use Yiisoft\Db\Contracts\ConnectionInterface;

/**
 * @property int $id
 * @property string $string_identifier
 */
class Alpha extends ActiveRecord
{
    public static function tableName()
    {
        return 'alpha';
    }

    public function getBetas()
    {
        return $this->hasMany(Beta::className(), ['alpha_string_identifier' => 'string_identifier']);
    }

    public static function getConnection(): ConnectionInterface
    {
        return ConnectionPool::getConnectionPool('mysql');
    }
}
