<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\MagicActiveRecord;

final class BoolAR extends MagicActiveRecord
{
    public function getTableName(): string
    {
        return 'bool_values';
    }
}
