<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Oracle\Stubs;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Order as AbstractOrder;

/**
 * Class Order.
 *
 * @property int $id
 * @property int $customer_id
 * @property int $created_at
 * @property string $total
 */
final class Order extends AbstractOrder
{
    public function getCustomer(): ActiveQuery
    {
        return $this->hasOne(Customer::class, ['id' => 'customer_id']);
    }
}
