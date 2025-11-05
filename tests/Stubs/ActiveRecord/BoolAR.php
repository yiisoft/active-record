<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

final class BoolAR extends ActiveRecord
{
    public int $id;
    public ?bool $bool_col = null;
    public bool $default_true = true;
    public bool $default_false = false;

    public function tableName(): string
    {
        return 'bool_values';
    }
}
