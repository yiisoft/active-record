<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class TestTrigger.
 *
 * @property int $id
 * @property string $stringcol
 */
final class TestTrigger extends ActiveRecord
{
    public function getTableName(): string
    {
        return 'test_trigger';
    }
}
