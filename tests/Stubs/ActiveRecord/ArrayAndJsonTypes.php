<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\Db\Expression\Value\ArrayValue;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Expression\Value\JsonValue;

final class ArrayAndJsonTypes extends ActiveRecord
{
    public int $id;
    public array|ArrayValue|null $intarray_col = null;
    public array|ArrayValue|null $textarray2_col = null;
    public array|float|int|string|JsonValue|null $json_col = null;
    public array|float|int|string|JsonValue|null $jsonb_col = null;
    public array|ArrayValue|Expression|null $jsonarray_col = null;
}
