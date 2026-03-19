<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveQuery;

use RuntimeException;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveRecordInterface;

final class SingleModelArrayActiveQuery extends ActiveQuery
{
    public function one(): array|ActiveRecordInterface|null
    {
        return ['id' => 1, 'name' => 'single-related-model'];
    }

    public function all(): array
    {
        throw new RuntimeException('all() should not be called after one() for a single related model.');
    }
}
