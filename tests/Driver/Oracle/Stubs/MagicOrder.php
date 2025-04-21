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
final class MagicOrder extends \Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\Order
{
    public function getCustomerQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasOne(MagicCustomer::class, ['id' => 'customer_id']);
    }
}
