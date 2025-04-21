<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Oracle\Stubs;

use Yiisoft\ActiveRecord\ActiveQuery;

/**
 * Class Customer.
 */
final class Customer extends \Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer
{
    protected string $ROWNUMID;

    public function getOrdersQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(Order::class, ['customer_id' => 'id'])->orderBy('{{customer}}.[[id]]');
    }
}
