<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\MagicalActiveRecord;

/**
 * Class TestTriggerAlert.
 *
 * @property int $id
 * @property string $stringcol
 */
final class TestTriggerAlert extends MagicalActiveRecord
{
    public function getTableName(): string
    {
        return 'test_trigger_alert';
    }
}
