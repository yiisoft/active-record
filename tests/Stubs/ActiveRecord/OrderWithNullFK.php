<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class Order.
 *
 * @property int $id
 * @property int|null $customer_id
 * @property int $created_at
 * @property float $total
 */
final class OrderWithNullFK extends ActiveRecord
{
    protected int $id;
    protected int|null $customer_id = null;
    protected int $created_at;
    protected float $total;

    public function getTableName(): string
    {
        return 'order_with_null_fk';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getCustomerId(): int|null
    {
        return $this->customer_id;
    }

    public function getCreatedAt(): int
    {
        return $this->created_at;
    }

    public function getTotal(): float
    {
        return $this->total;
    }
}
