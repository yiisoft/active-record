<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

/**
 * Class Profile.
 */
final class Profile extends ActiveRecord
{
    public const TABLE_NAME = 'profile';

    protected int $id;
    protected string $description;

    public function getTableName(): string
    {
        return self::TABLE_NAME;
    }
}
