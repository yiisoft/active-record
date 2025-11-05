<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

final class OrderItemWithNullFK extends ActiveRecord
{
    protected ?int $order_id = null;
    protected ?int $item_id = null;
    protected int $quantity;
    protected float $subtotal;

    public function tableName(): string
    {
        return 'order_item_with_null_fk';
    }
}
