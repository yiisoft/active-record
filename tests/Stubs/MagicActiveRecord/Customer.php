<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

/**
 * Class Customer.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $address
 * @property int $status
 */
class Customer extends MagicActiveRecord
{
    public const STATUS_ACTIVE = 1;
    public const STATUS_INACTIVE = 2;

    /**
     * @var int|string
     */
    public $status2;
    /**
     * @var int|string|null
     */
    public $sumTotal;

    public function getTableName(): string
    {
        return 'customer';
    }

    public function getName(): string
    {
        return $this->getAttribute('name');
    }

    public function getProfileQuery(): ActiveQuery
    {
        return $this->hasOne(Profile::class, ['id' => 'profile_id']);
    }

    public function getOrdersPlainQuery(): ActiveQuery
    {
        return $this->hasMany(Order::class, ['customer_id' => 'id']);
    }

    public function getOrdersQuery(): ActiveQuery
    {
        return $this->hasMany(Order::class, ['customer_id' => 'id'])->orderBy('[[id]]');
    }

    public function getOrdersNoOrderQuery(): ActiveQuery
    {
        return $this->hasMany(Order::class, ['customer_id' => 'id']);
    }

    public function getExpensiveOrdersQuery(): ActiveQuery
    {
        return $this->hasMany(Order::class, ['customer_id' => 'id'])->andWhere('[[total]] > 50')->orderBy('id');
    }

    public function getItemQuery(): void
    {
    }

    public function getOrdersWithItemsQuery(): ActiveQuery
    {
        return $this->hasMany(Order::class, ['customer_id' => 'id'])->with('orderItems');
    }

    public function getExpensiveOrdersWithNullFKQuery(): ActiveQuery
    {
        return $this->hasMany(
            OrderWithNullFK::class,
            ['customer_id' => 'id']
        )->andWhere('[[total]] > 50')->orderBy('id');
    }

    public function getOrdersWithNullFKQuery(): ActiveQuery
    {
        return $this->hasMany(OrderWithNullFK::class, ['customer_id' => 'id'])->orderBy('id');
    }

    public function getOrders2Query(): ActiveQuery
    {
        return $this->hasMany(Order::class, ['customer_id' => 'id'])->inverseOf('customer2')->orderBy('id');
    }

    /** deeply nested table relation */
    public function getOrderItemsQuery(): ActiveQuery
    {
        $rel = $this->hasMany(Item::class, ['id' => 'item_id']);

        return $rel->viaTable('order_item', ['order_id' => 'id'], function ($q) {
            /* @var $q ActiveQuery */
            $q->viaTable('order', ['customer_id' => 'id']);
        })->orderBy('id');
    }

    public function setOrdersReadOnly(): void
    {
    }

    public function getOrderItems2Query(): ActiveQuery
    {
        return $this->hasMany(OrderItem::class, ['order_id' => 'id'])
            ->via('ordersNoOrder');
    }

    public function getItems2Query(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['id' => 'item_id'])
            ->via('orderItems2');
    }
}
