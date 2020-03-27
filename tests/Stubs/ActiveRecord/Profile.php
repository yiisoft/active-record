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
class Profile extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'profile';
    }
}
