<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\MagicalActiveRecord;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * CustomerWithConstructor.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $address
 * @property int $status
 * @property ProfileWithConstructor $profile
 */
final class CustomerWithConstructor extends MagicalActiveRecord
{
    public function __construct(ConnectionInterface $db, private Aliases $aliases)
    {
        parent::__construct($db);
    }

    public function getTableName(): string
    {
        return 'customer';
    }

    public function getProfileQuery(): ActiveQuery
    {
        return $this->hasOne(Profile::class, ['id' => 'profile_id']);
    }
}
