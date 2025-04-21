<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecordModel;

final class DefaultPk extends ActiveRecordModel
{
    public int $id;
    public string $type;

    public function tableName(): string
    {
        return 'default_pk';
    }
}
