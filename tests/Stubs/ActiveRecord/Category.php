<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordModel;

/**
 * Class Category.
 */
final class Category extends ActiveRecordModel
{
    protected int|null $id;
    protected string $name;

    public function tableName(): string
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
        $this->activeRecord()->set('id', $id);
    }

    public function getLimitedItems(): array
    {
        return $this->activeRecord()->relation('limitedItems');
    }

    public function getLimitedItemsQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(Item::class, ['category_id' => 'id'])->onCondition(['item.id' => [1, 2, 3]]);
    }

    public function getItems(): array
    {
        return $this->activeRecord()->relation('items');
    }

    public function getItemsQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(Item::class, ['category_id' => 'id']);
    }

    public function getOrderItems(): array
    {
        return $this->activeRecord()->relation('orderItems');
    }

    public function getOrderItemsQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(OrderItem::class, ['item_id' => 'id'])->via('items');
    }

    public function getOrders(): array
    {
        return $this->activeRecord()->relation('orders');
    }

    public function getOrdersQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(Order::class, ['id' => 'order_id'])->via('orderItems');
    }
}
