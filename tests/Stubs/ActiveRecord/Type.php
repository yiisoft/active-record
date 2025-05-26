<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use DateTimeInterface;
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\Db\Expression\Expression;

/**
 * Model representing type table.
 */
class Type extends ActiveRecord
{
    public int $int_col;
    public int|null $int_col2 = 1;
    public int|null $tinyint_col = 1;
    public int|null $smallint_col = 1;
    public string $char_col;
    public string|null $char_col2 = 'something';
    public string|null $char_col3 = null;
    public float $float_col;
    public float|null $float_col2 = 1.23;
    public mixed $blob_col;
    public float|null $numeric_col = 33.22;
    public string|DateTimeInterface|Expression $time = '2002-01-01 00:00:00';
    public bool|int|string $bool_col;
    public bool|int|string|null $bool_col2 = true;
    public DateTimeInterface|Expression $ts_default;
    public int|string $bit_col = 0b1000_0010;
    public array|null $json_col = null;

    public function tableName(): string
    {
        return 'type';
    }
}
