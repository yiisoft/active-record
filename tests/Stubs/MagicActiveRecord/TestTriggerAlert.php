<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

/**
 * Class TestTriggerAlert.
 *
 * @property int $id
 * @property string $stringcol
 */
final class TestTriggerAlert extends MagicActiveRecord
{
    public function getTableName(): string
    {
        return 'test_trigger_alert';
    }
}
