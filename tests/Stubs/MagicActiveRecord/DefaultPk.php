<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

final class DefaultPk extends MagicActiveRecord
{
    public function tableName(): string
    {
        return 'default_pk';
    }
}
