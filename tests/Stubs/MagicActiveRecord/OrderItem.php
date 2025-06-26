<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

/**
 * Class OrderItem.
 *
 * @property int $order_id
 * @property int $item_id
 * @property int $quantity
 * @property string $subtotal
 */
final class OrderItem extends MagicActiveRecord
{
    public function tableName(): string
    {
        return 'order_item';
    }

    public function fields(): array
    {
        $fields = [];

        $fields['order_id'] = $this->get('order_id');
        $fields['item_id'] = $this->get('item_id');
        $fields['price'] = $this->get('subtotal') / $this->get('quantity');
        $fields['quantity'] = $this->get('quantity');
        $fields['subtotal'] = $this->get('subtotal');

        return $fields;
    }

    public function getOrderQuery(): ActiveQuery
    {
        return $this->hasOne(Order::class, ['id' => 'order_id']);
    }

    public function getItemQuery(): ActiveQuery
    {
        return $this->hasOne(Item::class, ['id' => 'item_id']);
    }

    public function getOrderItemCompositeWithJoinQuery(): ActiveQuery
    {
        /** relations used by testFindCompositeWithJoin() */
        return $this->hasOne(self::class, ['item_id' => 'item_id', 'order_id' => 'order_id' ])->joinWith('item');
    }

    public function getOrderItemCompositeNoJoinQuery(): ActiveQuery
    {
        return $this->hasOne(self::class, ['item_id' => 'item_id', 'order_id' => 'order_id' ]);
    }

    public function getCustomQuery(): ActiveQuery
    {
        return new ActiveQuery(Order::class);
    }
}
