<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class NullValues.
 */
final class NullValues extends ActiveRecord
{
    public int $id;
    public int|null $var1 = null;
    public int|null $var2 = null;
    public int|null $var3 = null;
    public string|null $stringcol = null;

    public function getTableName(): string
    {
        return 'null_values';
    }
}
