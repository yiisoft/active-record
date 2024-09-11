<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class Category.
 */
final class Category extends ActiveRecord
{
    protected int|null $id;
    protected string $name;

    public function getTableName(): string
    {
        return 'category';
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'items' => $this->getItemsQuery(),
            'limitedItems' => $this->getLimitedItemsQuery(),
            'orderItems' => $this->getOrderItemsQuery(),
            'orders' => $this->getOrdersQuery(),
            default => parent::relationQuery($name),
        };
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setId(int|null $id): void
    {
        $this->set('id', $id);
    }

    public function getLimitedItems(): array
    {
        return $this->relation('limitedItems');
    }

    public function getLimitedItemsQuery(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['category_id' => 'id'])->onCondition(['item.id' => [1, 2, 3]]);
    }

    public function getItems(): array
    {
        return $this->relation('items');
    }

    public function getItemsQuery(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['category_id' => 'id']);
    }

    public function getOrderItems(): array
    {
        return $this->relation('orderItems');
    }

    public function getOrderItemsQuery(): ActiveQuery
    {
        return $this->hasMany(OrderItem::class, ['item_id' => 'id'])->via('items');
    }

    public function getOrders(): array
    {
        return $this->relation('orders');
    }

    public function getOrdersQuery(): ActiveQuery
    {
        return $this->hasMany(Order::class, ['id' => 'order_id'])->via('orderItems');
    }
}
