<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecordModel;

/**
 * {@see https://github.com/yiisoft/yii2/issues/9006}
 */
final class BitValues extends ActiveRecordModel
{
    public int $id;
    public bool|int $val;
}
