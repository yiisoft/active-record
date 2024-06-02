<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

/**
 * Class OrderItem.
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
