<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\MagicalActiveRecord;

/**
 * @property int $id
 * @property string $alpha_string_identifier
 * @property Alpha $alpha
 */
final class Beta extends MagicalActiveRecord
{
    public function getTableName(): string
    {
        return 'beta';
    }

    public function getAlphaQuery(): ActiveQuery
    {
        return $this->hasOne(Alpha::class, ['string_identifier' => 'alpha_string_identifier']);
    }
}
