<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

final class UserAR extends ActiveRecord
{
    public const STATUS_DELETED = 0;
    public const STATUS_ACTIVE = 10;
    public const ROLE_USER = 10;

    public function getTableName(): string
    {
        return '{{%bool_user}}';
    }
}
