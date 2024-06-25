<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * CustomerWithConstructor.
 */
final class CustomerWithConstructor extends ActiveRecord
{
    protected int $id;
    protected string $email;
    protected string|null $name = null;
    protected string|null $address = null;
    protected int|null $status = 0;
    protected bool|string|null $bool_status = false;
    protected int|null $profile_id = null;

    public function __construct(ConnectionInterface $db, private Aliases|null $aliases = null)
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
