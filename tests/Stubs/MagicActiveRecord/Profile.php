<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

/**
 * Class Profile.
 *
 * @property int $id
 * @property string $description
 */
final class Profile extends MagicActiveRecord
{
    public const TABLE_NAME = 'profile';

    public function getTableName(): string
    {
        return self::TABLE_NAME;
    }
}
