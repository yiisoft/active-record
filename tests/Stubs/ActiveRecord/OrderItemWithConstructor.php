<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class OrderItem.
 */
final class OrderItemWithConstructor extends ActiveRecord
{
    public function __construct(
        protected int $order_id,
        protected int $item_id,
        protected int $quantity,
        protected float $subtotal,
    ) {}

    public function tableName(): string
    {
        return '{{%order_item}}';
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
        $this->set('order_id', $orderId);
    }

    public function setItemId(int $itemId): void
    {
        $this->set('item_id', $itemId);
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
            default => parent::relationQuery($name),
        };
    }

    public function getOrder(): ?OrderWithConstructor
    {
        return $this->relation('order');
    }

    public function getOrderQuery(): ActiveQuery
    {
        return $this->hasOne(OrderWithConstructor::class, ['id' => 'order_id']);
    }

    public function getItem(): ?Item
    {
        return $this->relation('item');
    }

    public function getItemQuery(): ActiveQuery
    {
        return $this->hasOne(Item::class, ['id' => 'item_id']);
    }
}
