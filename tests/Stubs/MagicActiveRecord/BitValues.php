<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\MagicActiveRecord;

/**
 * {@see https://github.com/yiisoft/yii2/issues/9006}
 *
 * @property int $id
 * @property int $val
 */
final class BitValues extends MagicActiveRecord
{
}
