<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecordModel;

/**
 * Class OrderItem.
 */
final class OrderItemWithNullFK extends ActiveRecordModel
{
    protected int|null $order_id = null;
    protected int|null $item_id = null;
    protected int $quantity;
    protected float $subtotal;

    public function tableName(): string
    {
        return 'order_item_with_null_fk';
    }
}
