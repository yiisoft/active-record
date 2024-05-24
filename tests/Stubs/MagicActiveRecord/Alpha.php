<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\MagicalActiveRecord;

/**
 * @property int $id
 * @property string $string_identifier
 */
final class Alpha extends MagicalActiveRecord
{
    public const TABLE_NAME = 'alpha';

    public function getTableName(): string
    {
        return self::TABLE_NAME;
    }

    public function getBetasQuery(): ActiveQuery
    {
        return $this->hasMany(Beta::class, ['alpha_string_identifier' => 'string_identifier']);
    }
}
