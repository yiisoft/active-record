<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class OrderItem.
 *
 * @property int|null $order_id
 * @property int|null $item_id
 * @property int $quantity
 * @property float $subtotal
 */
final class OrderItemWithNullFK extends ActiveRecord
{
    protected int|null $order_id = null;
    protected int|null $item_id = null;
    protected int $quantity;
    protected float $subtotal;

    public function getTableName(): string
    {
        return 'order_item_with_null_fk';
    }
}
