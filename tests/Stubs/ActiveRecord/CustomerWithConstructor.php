<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;
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
 */
final class CustomerWithConstructor extends ActiveRecord
{
    protected int $id;
    protected string $name;
    protected string $email;
    protected string $address;
    protected int $status;
    protected int $profile_id;

    public function __construct(ConnectionInterface $db, private Aliases $aliases)
    {
        parent::__construct($db);
    }

    public function getTableName(): string
    {
        return 'customer';
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'profile' => $this->getProfileQuery(),
            default => parent::relationQuery($name),
        };
    }

    public function getProfile(): Profile|null
    {
        return $this->relation('profile');
    }

    public function getProfileQuery(): ActiveQuery
    {
        return $this->hasOne(Profile::class, ['id' => 'profile_id']);
    }
}
