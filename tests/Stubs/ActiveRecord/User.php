<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;

final class User extends ActiveRecord
{
    protected int $id;
    protected string $username;

    public function tableName(): string
    {
        return 'user';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'profile' => $this->getProfileQuery(),
            default => parent::relationQuery($name),
        };
    }

    public function getProfile(): UserProfile|null
    {
        return $this->relation('profile');
    }

    public function getProfileQuery(): ActiveQuery
    {
        return $this->hasOne(UserProfile::class, ['id' => 'id']);
    }
}
