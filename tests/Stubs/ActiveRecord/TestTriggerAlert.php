<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class TestTriggerAlert.
 *
 * @property int $id
 * @property string $stringcol
 */
class TestTriggerAlert extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'test_trigger_alert';
    }
}
