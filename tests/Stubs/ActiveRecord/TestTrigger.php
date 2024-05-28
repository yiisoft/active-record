<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

/**
 * Class TestTrigger.
 */
final class TestTrigger extends ActiveRecord
{
    public int $id;
    public string $stringcol;

    public function getTableName(): string
    {
        return 'test_trigger';
    }
}
