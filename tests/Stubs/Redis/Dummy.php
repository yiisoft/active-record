<?php

namespace Yiisoft\ActiveRecord\Tests\Stubs\Redis;

use Yiisoft\ActiveRecord\Redis\ActiveRecord;

final class Dummy extends ActiveRecord
{
    public function primaryKey(): array
    {
        return [];
    }
}
