<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveQuery;

use Yiisoft\ActiveRecord\ActiveQuery;

final class OverriddenPrimaryTableNameActiveQuery extends ActiveQuery
{
    protected function getPrimaryTableName(): string
    {
        return 'profile';
    }
}
