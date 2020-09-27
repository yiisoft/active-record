<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class OrderItem.
 *
 * @property int $order_id
 * @property int $item_id
 * @property int $quantity
 * @property string $subtotal
 */
final class OrderItem extends ActiveRecord
{
    public ?string $tableName = null;

    public function tableName(): string
    {
        return $this->tableName ?: 'order_item';
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

    public function getOrder(): ActiveQuery
    {
        return $this->hasOne(Order::class, ['id' => 'order_id']);
    }

    public function getItem(): ActiveQuery
    {
        return $this->hasOne(Item::class, ['id' => 'item_id']);
    }

    public function getOrderItemCompositeWithJoin(): ActiveQuery
    {
        /** relations used by testFindCompositeWithJoin() */
        return $this->hasOne(self::class, ['item_id' => 'item_id', 'order_id' => 'order_id' ])->joinWith('item');
    }

    public function getOrderItemCompositeNoJoin(): ActiveQuery
    {
        return $this->hasOne(self::class, ['item_id' => 'item_id', 'order_id' => 'order_id' ]);
    }

    public function getCustom(): ActiveQuery
    {
        return (new Order($this->db))->find();
    }

    public function setTableName(?string $value): void
    {
        $this->tableName = $value;
    }
}
