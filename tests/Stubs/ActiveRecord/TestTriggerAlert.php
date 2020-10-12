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
final class TestTriggerAlert extends ActiveRecord
{
    public function tableName(): string
    {
        return 'test_trigger_alert';
    }
}
