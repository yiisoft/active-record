<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * @property int $id
 * @property string $string_identifier
 */
final class Alpha extends ActiveRecord
{
    public function tableName(): string
    {
        return 'alpha';
    }

    public function getBetas(): ActiveQuery
    {
        return $this->hasMany(Beta::class, ['alpha_string_identifier' => 'string_identifier']);
    }
}
