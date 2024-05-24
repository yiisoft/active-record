<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\MagicalActiveRecord;

final class NoExist extends MagicalActiveRecord
{
    public function getTableName(): string
    {
        return 'NoExist';
    }
}
