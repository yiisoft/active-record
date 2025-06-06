<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

/**
 * Model representing type table.
 *
 * @property int $int_col
 * @property int $int_col2 DEFAULT 1
 * @property int $smallint_col DEFAULT 1
 * @property string $char_col
 * @property string $char_col2 DEFAULT 'something'
 * @property string $char_col3
 * @property float $float_col
 * @property float $float_col2 DEFAULT '1.23'
 * @property string $blob_col
 * @property float $numeric_col DEFAULT '33.22'
 * @property string $time DEFAULT '2002-01-01 00:00:00'
 * @property bool $bool_col
 * @property bool $bool_col2 DEFAULT 1
 * @property mixed $json_col
 */
final class Type extends MagicActiveRecord
{
    public function tableName(): string
    {
        return 'type';
    }
}
