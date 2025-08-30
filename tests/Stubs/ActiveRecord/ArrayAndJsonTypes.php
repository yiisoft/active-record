<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\Db\Expression\Value\ArrayExpression;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Expression\Value\JsonExpression;

final class ArrayAndJsonTypes extends ActiveRecord
{
    public int $id;
    public array|ArrayExpression|null $intarray_col = null;
    public array|ArrayExpression|null $textarray2_col = null;
    public array|float|int|string|JsonExpression|null $json_col = null;
    public array|float|int|string|JsonExpression|null $jsonb_col = null;
    public array|ArrayExpression|Expression|null $jsonarray_col = null;
}
