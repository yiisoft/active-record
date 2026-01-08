<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Oracle\Stubs;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\Order;

/**
 * Class Order.
 *
 * @property int $id
 * @property int $customer_id
 * @property int $created_at
 * @property string $total
 */
final class MagicOrder extends Order
{
    public function getCustomerQuery(): ActiveQuery
    {
        return $this->hasOne(MagicCustomer::class, ['id' => 'customer_id']);
    }
}
