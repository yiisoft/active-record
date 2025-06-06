<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class TestTriggerAlert.
 */
final class TestTriggerAlert extends ActiveRecord
{
    public int $id;
    public string $stringcol;

    public function tableName(): string
    {
        return 'test_trigger_alert';
    }
}
