<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecordModel;

final class NoExist extends ActiveRecordModel
{
    public function tableName(): string
    {
        return 'NoExist';
    }
}
