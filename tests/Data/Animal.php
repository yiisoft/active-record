<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Data;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\Db\Connectors\ConnectionPool;
use Yiisoft\Db\Contracts\ConnectionInterface;

/**
 * Class Animal.
 *
 * @property int $id
 * @property string $type
 */
class Animal extends ActiveRecord
{
    public $does;

    public static function tableName()
    {
        return 'animal';
    }

    public function __construct()
    {
        $this->type = \get_called_class();
    }

    public function getDoes()
    {
        return $this->does;
    }

    /**
     * @param type $row
     * @return \Yii\Extensions\ActiveRecord\Tests\Data\Animal
     */
    public static function instantiate($row)
    {
        $class = $row['type'];
        return new $class();
    }

    public static function getConnection(): ConnectionInterface
    {
        return ConnectionPool::getConnectionPool('mysql');
    }
}
