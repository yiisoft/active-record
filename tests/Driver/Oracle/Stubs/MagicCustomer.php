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
 * @property bool $bool_status
 */
final class MagicCustomer extends \Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\Customer
{
    public function getOrdersQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(Order::class, ['customer_id' => 'id'])->orderBy('{{customer}}.[[id]]');
    }
}
