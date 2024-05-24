<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\MagicalActiveRecord;

final class BoolAR extends MagicalActiveRecord
{
    public function getTableName(): string
    {
        return 'bool_values';
    }
}
