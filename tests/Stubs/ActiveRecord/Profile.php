<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class Profile.
 *
 * @property int $id
 * @property string $description
 */
final class Profile extends ActiveRecord
{
    public const TABLE_NAME = 'profile';

    public function getTableName(): string
    {
        return self::TABLE_NAME;
    }
}
