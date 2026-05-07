<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class OrderItemWithDeepViaProfile.
 */
final class OrderItemWithDeepViaProfile extends ActiveRecord
{
    public int $order_id;
    public int $item_id;
    public int $quantity;
    public float $subtotal;

    public function tableName(): string
    {
        return 'order_item';
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'order' => $this->getOrderQuery(),
            'customerViaOrder' => $this->getCustomerViaOrderQuery(),
            'profileViaCustomerViaOrder' => $this->getProfileViaCustomerViaOrderQuery(),
            default => parent::relationQuery($name),
        };
    }

    public function getOrderQuery(): ActiveQuery
    {
        return $this->hasOne(Order::class, ['id' => 'order_id']);
    }

    public function getCustomerViaOrderQuery(): ActiveQuery
    {
        return $this->hasOne(Customer::class, ['id' => 'customer_id'])->via('order');
    }

    public function getProfileViaCustomerViaOrderQuery(): ActiveQuery
    {
        return $this->hasOne(Profile::class, ['id' => 'profile_id'])->via('customerViaOrder');
    }
}
