<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\Db\Expression\Expression;

/**
 * Model representing type table.
 *
 * @property int $int_col
 * @property int|null $int_col2 DEFAULT 1
 * @property int|null $tinyint_col DEFAULT 1
 * @property int|null $smallint_col DEFAULT 1
 * @property string $char_col
 * @property string|null $char_col2 DEFAULT 'something'
 * @property string|null $char_col3
 * @property float $float_col
 * @property float|null $float_col2 DEFAULT '1.23'
 * @property string|null $blob_col
 * @property float|null $numeric_col DEFAULT '33.22'
 * @property string $time DEFAULT '2002-01-01 00:00:00'
 * @property bool $bool_col
 * @property bool|null $bool_col2 DEFAULT 1
 */
final class Type extends ActiveRecord
{
    public int $int_col;
    public int|null $int_col2 = 1;
    public int|null $tinyint_col = 1;
    public int|null $smallint_col = 1;
    public string $char_col;
    public string|null $char_col2 = 'something';
    public string|null $char_col3;
    public float $float_col;
    public float|null $float_col2 = 1.23;
    public mixed $blob_col;
    public float|null $numeric_col = 33.22;
    public string $time = '2002-01-01 00:00:00';
    public bool|int $bool_col;
    public bool|int|null $bool_col2 = true;
    public string|Expression $ts_default;
    public $bit_col = b'10000010';
    public array $json_col;

    public function getTableName(): string
    {
        return 'type';
    }
}
