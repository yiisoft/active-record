<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

final class NoPk extends ActiveRecord
{
    public int $id;
    public int $customer_id;
    public string $name;

    public function tableName(): string
    {
        return 'no_pk';
    }
}
