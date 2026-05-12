<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveQuery;

use Yiisoft\ActiveRecord\ActiveQuery;

final class MissingLinkValuesActiveQuery extends ActiveQuery
{
    public function all(): array
    {
        return [['name' => 'orphan-profile']];
    }
}
