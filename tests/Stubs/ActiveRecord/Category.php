<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class Category.
 *
 * @property int $id
 * @property string $name
 */
final class Category extends ActiveRecord
{
    public function getTableName(): string
    {
        return 'category';
    }

    public function getLimitedItems(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['category_id' => 'id'])->onCondition(['item.id' => [1, 2, 3]]);
    }

    public function getItems(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['category_id' => 'id']);
    }

    public function getOrderItems(): ActiveQuery
    {
        return $this->hasMany(OrderItem::class, ['item_id' => 'id'])->via('items');
    }

    public function getOrders(): ActiveQuery
    {
        return $this->hasMany(Order::class, ['id' => 'order_id'])->via('orderItems');
    }
}
