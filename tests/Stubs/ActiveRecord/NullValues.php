<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecordModel;

/**
 * Class NullValues.
 *
 * @property int $id
 * @property int $var1
 * @property int $var2
 * @property int $var3
 * @property string $stringcol
 */
#[\AllowDynamicProperties]
final class NullValues extends ActiveRecordModel
{
    public function tableName(): string
    {
        return 'null_values';
    }
}
