<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

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
    public int $id;
    public int|null $var1;
    public int|null $var2;
    public int|null $var3;
    public string|null $stringcol;

    public function getTableName(): string
    {
        return 'null_values';
    }
}
