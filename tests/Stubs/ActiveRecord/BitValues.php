<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * {@see https://github.com/yiisoft/yii2/issues/9006}
 *
 * @property int $id
 * @property bool $val
 */
final class BitValues extends ActiveRecord
{
    public int $id;
    public bool $val;
}
