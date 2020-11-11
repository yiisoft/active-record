<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use ReflectionClass;
use Yiisoft\Aliases\Aliases;
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
    private Aliases $aliases;

    public function __construct(ConnectionInterface $db, Aliases $aliases)
    {
        parent::__construct($db);

        $this->aliases = $aliases;
    }

    public function tableName(): string
    {
        return 'customer';
    }

    public function getProfile(): ActiveQuery
    {
        return $this->hasOne(Profile::class, ['id' => 'profile_id']);
    }
}
