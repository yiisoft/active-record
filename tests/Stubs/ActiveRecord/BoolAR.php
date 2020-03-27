<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

class BoolAR extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'bool_values';
    }
}
