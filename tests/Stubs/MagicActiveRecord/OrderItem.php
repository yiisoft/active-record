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
        $fields = parent::fields();

        $fields['order_id'] = $this->activeRecord()->get('order_id');
        $fields['item_id'] = $this->activeRecord()->get('item_id');
        $fields['price'] = $this->activeRecord()->get('subtotal') / $this->activeRecord()->get('quantity');
        $fields['quantity'] = $this->activeRecord()->get('quantity');
        $fields['subtotal'] = $this->activeRecord()->get('subtotal');

        return $fields;
    }

    public function getOrderQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasOne(Order::class, ['id' => 'order_id']);
    }

    public function getItemQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasOne(Item::class, ['id' => 'item_id']);
    }

    public function getOrderItemCompositeWithJoinQuery(): ActiveQuery
    {
        /** relations used by testFindCompositeWithJoin() */
        return $this->activeRecord()->hasOne(self::class, ['item_id' => 'item_id', 'order_id' => 'order_id' ])->joinWith('item');
    }

    public function getOrderItemCompositeNoJoinQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasOne(self::class, ['item_id' => 'item_id', 'order_id' => 'order_id' ]);
    }

    public function getCustomQuery(): ActiveQuery
    {
        return new ActiveQuery(Order::class);
    }
}
