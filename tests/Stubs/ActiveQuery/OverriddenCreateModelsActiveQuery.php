<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveQuery;

use Yiisoft\ActiveRecord\ActiveQuery;

final class OverriddenCreateModelsActiveQuery extends ActiveQuery
{
    protected function createModels(array $rows): array
    {
        return [['overridden' => true, 'rows' => $rows]];
    }
}
