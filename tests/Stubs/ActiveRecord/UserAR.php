<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

final class UserAR extends ActiveRecord
{
    public const STATUS_DELETED = 0;
    public const STATUS_ACTIVE = 10;
    public const ROLE_USER = 10;

    public int $id;
    public string $username;
    public string $auth_key;
    public string $password_hash;
    public ?string $password_reset_token = null;
    public string $email;
    public int $role = 10;
    public int $status = 10;
    public int $created_at;
    public int $updated_at;
    public bool $is_deleted = false;

    public function tableName(): string
    {
        return '{{%bool_user}}';
    }
}
