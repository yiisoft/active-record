<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use ReflectionClass;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * OrderWithConstructor.
 *
 * @property int $id
 * @property int $customer_id
 * @property int $created_at
 * @property string $total
 *
 * @property OrderItemWithConstructor $orderItems
 * @property CustomerWithConstructor $customer
 * @property CustomerWithConstructor $customerJoinedWithProfile
 */
final class OrderWithConstructor extends ActiveRecord
{
    private ConnectionInterface $db;

    public function tableName(): string
    {
        return 'order';
    }

    public function __construct(ConnectionInterface $db, int $id)
    {
        $this->id = $id;
        $this->created_at = time();
        $this->db = $db;
        parent::__construct($db);
    }

    public function instance($refresh = false): self
    {
        return $this->instantiate();
    }

    public function instantiate(): ActiveRecord
    {
        return (new ReflectionClass(static::class))->newInstanceWithoutConstructor();
    }

    public function getCustomer(): ActiveQuery
    {
        return $this->hasOne(CustomerWithConstructor::class, ['id' => 'customer_id']);
    }

    public function getCustomerJoinedWithProfile(): ActiveQuery
    {
        return $this->hasOne(CustomerWithConstructor::class, ['id' => 'customer_id'])
            ->joinWith('profile');
    }

    public function getOrderItems(): ActiveQuery
    {
        return $this->hasMany(OrderItemWithConstructor::class, ['order_id' => 'id'])->inverseOf('order');
    }
}
