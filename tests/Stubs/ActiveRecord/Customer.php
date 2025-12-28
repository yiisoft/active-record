<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use DateTimeImmutable;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\ActiveRecord\Tests\Stubs\ArrayableActiveRecord;
use Yiisoft\ActiveRecord\Trait\RepositoryTrait;

/**
 * Class Customer.
 */
class Customer extends ArrayableActiveRecord
{
    use RepositoryTrait;

    public const STATUS_ACTIVE = 1;
    public const STATUS_INACTIVE = 2;

    public int|string $status2;
    public float|string|null $sumTotal;

    protected int $id;
    protected string $email;
    protected ?string $name = null;
    protected ?string $address = null;
    protected ?int $status = 0;
    protected bool|int|null $bool_status = false;
    protected ?DateTimeImmutable $registered_at = null;
    protected ?int $profile_id = null;

    public function tableName(): string
    {
        return 'customer';
    }

    public function fields(): array
    {
        return array_merge(parent::fields(), [
            'registered_at' => static fn(self $customer) => $customer->registered_at->format('Y-m-d\TH:i:s.uP'),
        ]);
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'profile' => $this->getProfileQuery(),
            'orders' => $this->getOrdersQuery(),
            'ordersPlain' => $this->getOrdersPlainQuery(),
            'ordersNoOrder' => $this->getOrdersNoOrderQuery(),
            'expensiveOrders' => $this->getExpensiveOrdersQuery(),
            'ordersWithItems' => $this->getOrdersWithItemsQuery(),
            'expensiveOrdersWithNullFK' => $this->getExpensiveOrdersWithNullFKQuery(),
            'ordersWithNullFK' => $this->getOrdersWithNullFKQuery(),
            'orders2' => $this->getOrders2Query(),
            'ordersIndexedWithInverseOf' => $this->getOrdersIndexedWithInverseOfQuery(),
            'orderItems' => $this->getOrderItemsQuery(),
            'orderItems2' => $this->getOrderItems2Query(),
            'orderItemsIndexedByClosure' => $this->getOrderItemsIndexedByClosureQuery(),
            'items2' => $this->getItems2Query(),
            'ordersUsingInstance' => $this->hasMany(new Order(), ['customer_id' => 'id']),
            default => parent::relationQuery($name),
        };
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function getBoolStatus(): ?bool
    {
        return $this->bool_status;
    }

    public function getRegisteredAt(): ?DateTimeImmutable
    {
        return $this->registered_at;
    }

    public function getProfileId(): ?int
    {
        return $this->profile_id;
    }

    public function setId(?int $id): void
    {
        if ($id === null) {
            unset($this->id);
            return;
        }
        $this->id = $id;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function setAddress(?string $address): void
    {
        $this->address = $address;
    }

    public function setStatus(?int $status): void
    {
        $this->status = $status;
    }

    public function setBoolStatus(?bool $bool_status): void
    {
        $this->bool_status = $bool_status;
    }

    public function setRegisteredAt(?DateTimeImmutable $registered_at): void
    {
        $this->registered_at = $registered_at;
    }

    public function setProfileId(?int $profile_id): void
    {
        $this->set('profile_id', $profile_id);
    }

    public function getProfile(): ?Profile
    {
        return $this->relation('profile');
    }

    public function getProfileQuery(): ActiveQuery
    {
        return $this->hasOne(Profile::class, ['id' => 'profile_id']);
    }

    public function getOrdersPlain(): array
    {
        return $this->relation('ordersPlain');
    }

    public function getOrdersPlainQuery(): ActiveQuery
    {
        return $this->hasMany(Order::class, ['customer_id' => 'id']);
    }

    public function getOrders(): array
    {
        return $this->relation('orders');
    }

    public function getOrdersQuery(): ActiveQuery
    {
        return $this->hasMany(Order::class, ['customer_id' => 'id'])->orderBy('[[id]]');
    }

    public function getOrdersNoOrder(): array
    {
        return $this->relation('ordersNoOrder');
    }

    public function getOrdersNoOrderQuery(): ActiveQuery
    {
        return $this->hasMany(Order::class, ['customer_id' => 'id']);
    }

    public function getExpensiveOrders(): array
    {
        return $this->relation('expensiveOrders');
    }

    public function getExpensiveOrdersQuery(): ActiveQuery
    {
        return $this->hasMany(Order::class, ['customer_id' => 'id'])->andWhere('[[total]] > 50')->orderBy('id');
    }

    public function getItem(): void {}

    public function getOrdersWithItems(): array
    {
        return $this->relation('ordersWithItems');
    }

    public function getOrdersWithItemsQuery(): ActiveQuery
    {
        return $this->hasMany(Order::class, ['customer_id' => 'id'])->with('orderItems');
    }

    public function getExpensiveOrdersWithNullFK(): array
    {
        return $this->relation('expensiveOrdersWithNullFK');
    }

    public function getExpensiveOrdersWithNullFKQuery(): ActiveQuery
    {
        return $this->hasMany(
            OrderWithNullFK::class,
            ['customer_id' => 'id'],
        )->andWhere('[[total]] > 50')->orderBy('id');
    }

    public function getOrdersWithNullFK(): array
    {
        return $this->relation('ordersWithNullFK');
    }

    public function getOrdersWithNullFKQuery(): ActiveQuery
    {
        return $this->hasMany(OrderWithNullFK::class, ['customer_id' => 'id'])->orderBy('id');
    }

    public function getOrders2(): array
    {
        return $this->relation('orders2');
    }

    public function getOrders2Query(): ActiveQuery
    {
        return $this->hasMany(Order::class, ['customer_id' => 'id'])->inverseOf('customer2')->orderBy('id');
    }

    public function getOrdersIndexedWithInverseOf(): array
    {
        return $this->relation('ordersIndexedWithInverseOf');
    }

    public function getOrdersIndexedWithInverseOfQuery(): ActiveQuery
    {
        return $this->hasMany(Order::class, ['customer_id' => 'id'])
            ->inverseOf('customerIndexedWithInverseOf')
            ->indexBy('id');
    }

    public function getOrderItems(): array
    {
        return $this->relation('orderItems');
    }

    /** deeply nested table relation */
    public function getOrderItemsQuery(): ActiveQuery
    {
        $rel = $this->hasMany(Item::class, ['id' => 'item_id']);

        return $rel->viaTable('order_item', ['order_id' => 'id'], function ($q) {
            /* @var $q ActiveQuery */
            $q->viaTable('order', ['customer_id' => 'id']);
        })->orderBy('id');
    }

    public function getOrderItems2(): array
    {
        return $this->relation('orderItems2');
    }

    public function getOrderItems2Query(): ActiveQuery
    {
        return $this->hasMany(OrderItem::class, ['order_id' => 'id'])
            ->via('ordersNoOrder');
    }

    public function getOrderItemsIndexedByClosure(): array
    {
        return $this->relation('orderItemsIndexedByClosure');
    }

    public function getOrderItemsIndexedByClosureQuery(): ActiveQuery
    {
        return $this
            ->hasMany(Order::class, ['customer_id' => 'id'])
            ->indexBy(fn(Order $order) => 'order_' . $order->getId());
    }

    public function getItems2(): array
    {
        return $this->relation('items2');
    }

    public function getItems2Query(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['id' => 'item_id'])
            ->via('orderItems2');
    }

    public function getOrdersUsingInstance(): array
    {
        return $this->relation('ordersUsingInstance');
    }

    public static function query(ActiveRecordInterface|string|null $modelClass = null): ActiveQueryInterface
    {
        return new CustomerQuery($modelClass ?? static::class);
    }
}
