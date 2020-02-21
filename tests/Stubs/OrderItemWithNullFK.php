<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs;

/**
 * Class OrderItem.
 *
 * @property int $order_id
 * @property int $item_id
 * @property int $quantity
 * @property string $subtotal
 */
class OrderItemWithNullFK extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'order_item_with_null_fk';
    }
}
