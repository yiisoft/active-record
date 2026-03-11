<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use DateTimeInterface;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Event\Handler\DefaultDateTimeOnInsert;
use Yiisoft\ActiveRecord\Event\Handler\SetDateTimeOnUpdate;
use Yiisoft\ActiveRecord\Event\Handler\SoftDelete;
use Yiisoft\ActiveRecord\Trait\EventsTrait;
use Yiisoft\ActiveRecord\Trait\RepositoryTrait;

/**
 * Class Order.
 */
class OrderWithConstructor extends ActiveRecord
{
    use EventsTrait;
    use RepositoryTrait;

    protected ?int $id;
    #[DefaultDateTimeOnInsert]
    protected int|DateTimeInterface $created_at;
    #[DefaultDateTimeOnInsert]
    #[SetDateTimeOnUpdate]
    protected int|DateTimeInterface $updated_at;
    #[SoftDelete]
    protected int|DateTimeInterface|null $deleted_at;

    public function __construct(
        protected int $customer_id,
        protected float $total = 0.0,
    ) {}

    public function tableName(): string
    {
        return '{{%order}}';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomerId(): int
    {
        return $this->customer_id;
    }

    public function getCreatedAt(): int|DateTimeInterface
    {
        return $this->created_at;
    }

    public function getTotal(): float
    {
        return $this->total;
    }

    public function getUpdatedAt(): int|DateTimeInterface
    {
        return $this->updated_at;
    }

    public function getDeletedAt(): int|DateTimeInterface|null
    {
        return $this->deleted_at ?? null;
    }

    public function setId(?int $id): void
    {
        $this->set('id', $id);
    }

    public function setCustomerId(int $customerId): void
    {
        $this->set('customer_id', $customerId);
    }

    public function setCreatedAt(int|DateTimeInterface $createdAt): void
    {
        $this->created_at = $createdAt;
    }

    public function setUpdatedAt(int|DateTimeInterface $updatedAt): void
    {
        $this->updated_at = $updatedAt;
    }

    public function setTotal(float $total): void
    {
        $this->total = $total;
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'customer' => $this->getCustomerQuery(),
            'orderItems' => $this->getOrderItemsQuery(),
            default => parent::relationQuery($name),
        };
    }

    public function getCustomer(): ?Customer
    {
        return $this->relation('customer');
    }

    public function getCustomerQuery(): ActiveQuery
    {
        return $this->hasOne(Customer::class, ['id' => 'customer_id']);
    }

    public function getOrderItems(): array
    {
        return $this->relation('orderItems');
    }

    public function getOrderItemsQuery(): ActiveQuery
    {
        return $this->hasMany(OrderItemWithConstructor::class, ['order_id' => 'id']);
    }
}
