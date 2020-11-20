<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\Redis;

use Yiisoft\ActiveRecord\Redis\ActiveRecord;

/**
 * Class OrderItem
 *
 * @property int $order_id
 * @property int $item_id
 * @property int $quantity
 * @property string $subtotal
 */
final class OrderItemWithNullFK extends ActiveRecord
{
    public function attributes(): array
    {
        return ['order_id', 'item_id', 'quantity', 'subtotal'];
    }

    public function primaryKey(): array
    {
        return ['order_id', 'item_id'];
    }
}
