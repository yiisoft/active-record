<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

final class BoolAR extends ActiveRecord
{
    public function getTableName(): string
    {
        return 'bool_values';
    }
}
