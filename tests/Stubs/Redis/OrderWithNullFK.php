<?php

namespace Yiisoft\ActiveRecord\Tests\Stubs\Redis;

use Yiisoft\ActiveRecord\Redis\ActiveRecord;

/**
 * Class Order
 *
 * @property integer $id
 * @property integer $customer_id
 * @property integer $created_at
 * @property string $total
 */
final class OrderWithNullFK extends ActiveRecord
{
    public function attributes(): array
    {
        return ['id', 'customer_id', 'created_at', 'total'];
    }

    public static function primaryKey(): array
    {
        return ['id'];
    }
}
