<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use ReflectionClass;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\Db\Connection\ConnectionInterface;

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
final class CustomerWithConstructor extends ActiveRecord
{
    public function tableName(): string
    {
        return 'customer';
    }

    public function __construct(ConnectionInterface $db, string $name, string $email, string $address)
    {
        $this->name = $name;
        $this->email = $email;
        $this->address = $address;

        parent::__construct($db);
    }

    public function instance($refresh = false): ActiveRecord
    {
        return self::instantiate([]);
    }

    public static function instantiate($row): ActiveRecord
    {
        return (new ReflectionClass(static::class))->newInstanceWithoutConstructor();
    }

    public function getProfile(): ActiveQuery
    {
        return $this->hasOne(ProfileWithConstructor::class, ['id' => 'profile_id']);
    }
}
