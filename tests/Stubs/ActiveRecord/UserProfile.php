<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;

final class UserProfile extends ActiveRecord
{
    protected int $id;
    protected string $bio;

    public function tableName(): string
    {
        return 'user_profile';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getBio(): string
    {
        return $this->bio;
    }

    public function setBio(string $bio): void
    {
        $this->bio = $bio;
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'user' => $this->getUserQuery(),
            default => parent::relationQuery($name),
        };
    }

    public function getUser(): ?User
    {
        return $this->relation('user');
    }

    public function getUserQuery(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'id']);
    }
}
