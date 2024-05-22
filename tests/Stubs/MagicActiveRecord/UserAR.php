<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\MagicActiveRecord;

final class UserAR extends MagicActiveRecord
{
    public const STATUS_DELETED = 0;
    public const STATUS_ACTIVE = 10;
    public const ROLE_USER = 10;

    public function getTableName(): string
    {
        return '{{%bool_user}}';
    }
}
