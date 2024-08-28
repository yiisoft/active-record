<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\CustomTableNameTrait;

/**
 * Class OrderItem.
 */
final class OrderItem extends ActiveRecord
{
    use CustomTableNameTrait;

    protected int $order_id;
    protected int $item_id;
    protected int $quantity;
    protected float $subtotal;

    public function getTableName(): string
    {
        return $this->tableName ??= 'order_item';
    }

    public function getOrderId(): int
    {
        return $this->order_id;
    }

    public function getItemId(): int
    {
        return $this->item_id;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getSubtotal(): float
    {
        return $this->subtotal;
    }

    public function setOrderId(int $orderId): void
    {
        $this->setAttribute('order_id', $orderId);
    }

    public function setItemId(int $itemId): void
    {
        $this->setAttribute('item_id', $itemId);
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function setSubtotal(float $subtotal): void
    {
        $this->subtotal = $subtotal;
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'order' => $this->getOrderQuery(),
            'item' => $this->getItemQuery(),
            'orderItemCompositeWithJoin' => $this->getOrderItemCompositeWithJoinQuery(),
            'orderItemCompositeNoJoin' => $this->getOrderItemCompositeNoJoinQuery(),
            'custom' => $this->getCustomQuery(),
            default => parent::relationQuery($name),
        };
    }

    public function getOrder(): Order|null
    {
        return $this->relation('order');
    }

    public function getOrderQuery(): ActiveQuery
    {
        return $this->hasOne(Order::class, ['id' => 'order_id']);
    }

    public function getItem(): Item|null
    {
        return $this->relation('item');
    }

    public function getItemQuery(): ActiveQuery
    {
        return $this->hasOne(Item::class, ['id' => 'item_id']);
    }

    public function getOrderItemCompositeWithJoin(): self|null
    {
        return $this->relation('orderItemCompositeWithJoin');
    }

    public function getOrderItemCompositeWithJoinQuery(): ActiveQuery
    {
        /** relations used by testFindCompositeWithJoin() */
        return $this->hasOne(self::class, ['item_id' => 'item_id', 'order_id' => 'order_id' ])->joinWith('item');
    }

    public function getOrderItemCompositeNoJoin(): self|null
    {
        return $this->relation('orderItemCompositeNoJoin');
    }

    public function getOrderItemCompositeNoJoinQuery(): ActiveQuery
    {
        return $this->hasOne(self::class, ['item_id' => 'item_id', 'order_id' => 'order_id' ]);
    }

    public function getCustom(): Order|null
    {
        return $this->relation('custom');
    }

    public function getCustomQuery(): ActiveQuery
    {
        return new ActiveQuery(Order::class);
    }
}
