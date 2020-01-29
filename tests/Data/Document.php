<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Data;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\Db\Connectors\ConnectionPool;
use Yiisoft\Db\Contracts\ConnectionInterface;

/**
 * @property int $id
 * @property string $title
 * @property string $content
 * @property int $version
 * @property array $properties
 */
class Document extends ActiveRecord
{
    public function optimisticLock()
    {
        return 'version';
    }

    public static function getConnection(): ConnectionInterface
    {
        return ConnectionPool::getConnectionPool('mysql');
    }
}
