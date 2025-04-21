<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Oracle\Stubs;

use Yiisoft\ActiveRecord\ActiveQuery;

/**
 * Class Order.
 *
 * @property int $id
 * @property int $customer_id
 * @property int $created_at
 * @property string $total
 */
final class Order extends \Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Order
{
    public function getCustomerQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasOne(Customer::class, ['id' => 'customer_id']);
    }
}
