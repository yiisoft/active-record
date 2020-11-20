<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\Redis;

use Yiisoft\ActiveRecord\Redis\ActiveRecord;

/**
 * Class Order
 *
 * @property int $id
 * @property int $customer_id
 * @property int $created_at
 * @property string $total
 */
final class OrderWithNullFK extends ActiveRecord
{
    public function attributes(): array
    {
        return ['id', 'customer_id', 'created_at', 'total'];
    }

    public function primaryKey(): array
    {
        return ['id'];
    }
}
