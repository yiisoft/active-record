<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveQuery;

use RuntimeException;
use Yiisoft\ActiveRecord\ActiveQuery;

final class CreateModelsExceptionOnEmptyRowsActiveQuery extends ActiveQuery
{
    protected function createModels(array $rows): array
    {
        throw new RuntimeException('createModels() should not be called for empty rows.');
    }
}
