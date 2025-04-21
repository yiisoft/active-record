<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecordModel;

/**
 * Class Profile.
 */
final class Profile extends ActiveRecordModel
{
    public const TABLE_NAME = 'profile';

    protected int $id;
    protected string $description;

    public function tableName(): string
    {
        return self::TABLE_NAME;
    }
}
