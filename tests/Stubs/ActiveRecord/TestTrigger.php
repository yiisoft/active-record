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
class TestTrigger extends ActiveRecord
{
    public static function tableName()
    {
        return 'test_trigger';
    }
}
