<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\Db\Expression\ArrayExpression;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Expression\JsonExpression;

final class ArrayAndJsonTypes extends ActiveRecord
{
    public int $id;
    public array|ArrayExpression|null $intarray_col;
    public array|ArrayExpression|null $textarray2_col;
    public array|float|int|string|JsonExpression|null $json_col;
    public array|float|int|string|JsonExpression|null $jsonb_col;
    public array|ArrayExpression|Expression|null $jsonarray_col;
}
