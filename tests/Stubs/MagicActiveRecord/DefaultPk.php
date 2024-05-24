<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\MagicalActiveRecord;

final class DefaultPk extends MagicalActiveRecord
{
    public function getTableName(): string
    {
        return 'default_pk';
    }
}
