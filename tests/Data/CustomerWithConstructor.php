<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Data;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\Db\Connectors\ConnectionPool;
use Yiisoft\Db\Contracts\ConnectionInterface;

/**
 * CustomerWithConstructor.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $address
 * @property int $status
 *
 * @property ProfileWithConstructor $profile
 */
class CustomerWithConstructor extends ActiveRecord
{
    public static function tableName()
    {
        return 'customer';
    }

    public function __construct($name, $email, $address, $config = [])
    {
        $this->name = $name;
        $this->email = $email;
        $this->address = $address;
    }

    public static function instance($refresh = false)
    {
        return self::instantiate([]);
    }

    public static function instantiate($row)
    {
        return (new \ReflectionClass(static::class))->newInstanceWithoutConstructor();
    }

    public function getProfile()
    {
        return $this->hasOne(ProfileWithConstructor::class, ['id' => 'profile_id']);
    }

    public static function getConnection(): ConnectionInterface
    {
        return ConnectionPool::getConnectionPool('mysql');
    }
}
