<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\Redis;

use Yiisoft\ActiveRecord\Redis\ActiveQuery;
use Yiisoft\ActiveRecord\Redis\ActiveRecord;

/**
 * Class OrderItem
 *
 * @property int $order_id
 * @property int $item_id
 * @property int $quantity
 * @property string $subtotal
 *
 * @property Order $order
 * @property Item $item
 */
final class OrderItem extends ActiveRecord
{
    public function attributes(): array
    {
        return [
            'id',
            'order_id',
            'item_id',
            'quantity',
            'subtotal'
        ];
    }

    public function primaryKey(): array
    {
        return ['order_id', 'item_id'];
    }

    public function getOrder(): ActiveQuery
    {
        return $this->hasOne(Order::class, ['id' => 'order_id']);
    }

    public function getItem(): ActiveQuery
    {
        return $this->hasOne(Item::class, ['id' => 'item_id']);
    }

    /** relations used by ::testFindCompositeWithJoin() */
    public function getOrderItemCompositeWithJoin(): ActiveQuery
    {
        return $this->hasOne(self::class, ['item_id' => 'item_id', 'order_id' => 'order_id' ])
            ->joinWith('item');
    }

    public function getOrderItemCompositeNoJoin(): ActiveQuery
    {
        return $this->hasOne(self::class, ['item_id' => 'item_id', 'order_id' => 'order_id' ]);
    }

    public function getCustom(): ActiveQuery
    {
        return Order::find();
    }
}
