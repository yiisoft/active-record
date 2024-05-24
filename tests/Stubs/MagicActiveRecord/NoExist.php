<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\MagicActiveRecord;

final class NoExist extends MagicActiveRecord
{
    public function getTableName(): string
    {
        return 'NoExist';
    }
}
