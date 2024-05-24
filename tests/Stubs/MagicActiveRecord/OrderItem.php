<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\MagicActiveRecord;

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
    public function getTableName(): string
    {
        return 'order_item';
    }

    public function fields(): array
    {
        $fields = parent::fields();

        $fields['order_id'] = $this->getAttribute('order_id');
        $fields['item_id'] = $this->getAttribute('item_id');
        $fields['price'] = $this->getAttribute('subtotal') / $this->getAttribute('quantity');
        $fields['quantity'] = $this->getAttribute('quantity');
        $fields['subtotal'] = $this->getAttribute('subtotal');

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
        return new ActiveQuery(Order::class, $this->db());
    }
}
