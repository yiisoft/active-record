<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Oracle\Stubs;

use Yiisoft\ActiveRecord\ActiveQuery;

/**
 * Class Customer.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $address
 * @property int $status
 *
 * @method CustomerQuery findBySql($sql, $params = []) static.
 */
final class Customer extends \Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer
{
    public function getOrders(): ActiveQuery
    {
        return $this->hasMany(Order::class, ['customer_id' => 'id'])->orderBy('{{customer}}.[[id]]');
    }
}
