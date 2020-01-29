<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Data;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\Db\Connectors\ConnectionPool;
use Yiisoft\Db\Contracts\ConnectionInterface;

/**
 * Class Item.
 *
 * @property int $id
 * @property string $name
 * @property int $category_id
 */
class Item extends ActiveRecord
{
    public static function tableName()
    {
        return 'item';
    }

    public function getCategory()
    {
        return $this->hasOne(Category::class, ['id' => 'category_id']);
    }

    public static function getConnection(): ConnectionInterface
    {
        return ConnectionPool::getConnectionPool('mysql');;
    }
}
