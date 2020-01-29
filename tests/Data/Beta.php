<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Data;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\Db\Connectors\ConnectionPool;
use Yiisoft\Db\Contracts\ConnectionInterface;

/**
 * @property int $id
 * @property string $alpha_string_identifier
 * @property Alpha $alpha
 */
class Beta extends ActiveRecord
{
    public static function tableName()
    {
        return 'beta';
    }

    public function getAlpha()
    {
        return $this->hasOne(Alpha::class, ['string_identifier' => 'alpha_string_identifier']);
    }

    public static function getConnection(): ConnectionInterface
    {
        return ConnectionPool::getConnectionPool('mysql');
    }
}
