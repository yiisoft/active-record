<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\Redis;

use Yiisoft\ActiveRecord\Redis\ActiveQuery;
use Yiisoft\ActiveRecord\Redis\ActiveRecord;

/**
 * Customer
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $address
 * @property int $status
 *
 * @method CustomerQuery findBySql($sql, $params = []) static
 *
 * @property Order[] $orders
 * @property Order[] $expensiveOrders
 * @property Order[] $expensiveOrdersWithNullFK
 * @property Order[] $ordersWithNullFK
 * @property Order[] $ordersWithItems
 */
final class Customer extends ActiveRecord
{
    public const STATUS_ACTIVE = 1;
    public const STATUS_INACTIVE = 2;

    public $status2;
    public $sumTotal;

    public function attributes(): array
    {
        return [
            'id',
            'name',
            'email',
            'address',
            'status'
        ];
    }

    public function getProfile(): ActiveQuery
    {
        return $this->hasOne(Profile::class, ['id' => 'profile_id']);
    }

    public function getOrdersPlain(): ActiveQuery
    {
        return $this->hasMany(Order::class, ['customer_id' => 'id']);
    }

    public function getOrders(): ActiveQuery
    {
        return $this->hasMany(Order::class, ['customer_id' => 'id']);
    }

    public function getExpensiveOrders(): ActiveQuery
    {
        return $this->hasMany(Order::class, ['customer_id' => 'id'])->andWhere(['total' => 110]);
    }

    public function getOrdersWithItems(): ActiveQuery
    {
        return $this->hasMany(Order::class, ['customer_id' => 'id'])->with('orderItems');
    }

    public function getExpensiveOrdersWithNullFK(): ActiveQuery
    {
        return $this->hasMany(OrderWithNullFK::class, ['customer_id' => 'id'])->andwhere(['total' => 110]);
    }

    public function getOrdersWithNullFK(): ActiveQuery
    {
        return $this->hasMany(OrderWithNullFK::class, ['customer_id' => 'id']);
    }

    public function getOrders2(): ActiveQuery
    {
        return $this->hasMany(Order::class, ['customer_id' => 'id'])->inverseOf('customer2')->orderBy('id');
    }

    public function getOrderItems()
    {
        return $this->hasMany(Item::class, ['id' => 'item_id'])->via('orders');
    }

    public function find(): CustomerQuery
    {
        return new CustomerQuery(static::class, $this->getDb());
    }
}
