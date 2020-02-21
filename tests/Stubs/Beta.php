<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs;

use Yiisoft\ActiveRecord\ActiveQuery;

/**
 * @property int $id
 * @property string $alpha_string_identifier
 * @property Alpha $alpha
 */
class Beta extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'beta';
    }

    public function getAlpha(): ActiveQuery
    {
        return $this->hasOne(Alpha::class, ['string_identifier' => 'alpha_string_identifier']);
    }
}
