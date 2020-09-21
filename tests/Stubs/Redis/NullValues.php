<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\Redis;

use Yiisoft\ActiveRecord\Redis\ActiveRecord;

/**
 * Class NullValues.
 *
 * @property int $id
 * @property int $var1
 * @property int $var2
 * @property int $var3
 * @property string $stringcol
 */
final class NullValues extends ActiveRecord
{
    public function attributes(): array
    {
        return [
            'id',
            'var1',
            'var2',
            'var3',
            'stringcol'
        ];
    }
}
