<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordModel;
use Yiisoft\ActiveRecord\Trait\CustomTableNameTrait;

/**
 * Class Order.
 */
class Order extends ActiveRecordModel
{
    use CustomTableNameTrait;

    public const TABLE_NAME = 'order';

    protected int|null $id;
    protected int $customer_id;
    protected int $created_at;
    protected float $total;

    protected string|int|null $virtualCustomerId = null;

    public function tableName(): string
    {
        return $this->tableName ??= self::TABLE_NAME;
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getCustomerId(): int
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

    public function setId(int|null $id): void
    {
        $this->activeRecord()->set('id', $id);
    }

    public function setCustomerId(int $customerId): void
    {
        $this->activeRecord()->set('customer_id', $customerId);
    }

    public function setCreatedAt(int $createdAt): void
    {
        $this->created_at = $createdAt;
    }

    public function setTotal(float $total): void
    {
        $this->total = $total;
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'virtualCustomer' => $this->getVirtualCustomerQuery(),
            'customer' => $this->getCustomerQuery(),
            'customerJoinedWithProfile' => $this->getCustomerJoinedWithProfileQuery(),
            'customerJoinedWithProfileIndexOrdered' => $this->getCustomerJoinedWithProfileIndexOrderedQuery(),
            'customer2' => $this->getCustomer2Query(),
            'orderItems' => $this->getOrderItemsQuery(),
            'orderItems2' => $this->getOrderItems2Query(),
            'orderItems3' => $this->getOrderItems3Query(),
            'orderItemsWithNullFK' => $this->getOrderItemsWithNullFKQuery(),
            'items' => $this->getItemsQuery(),
            'itemsIndexed' => $this->getItemsIndexedQuery(),
            'itemsWithNullFK' => $this->getItemsWithNullFKQuery(),
            'itemsInOrder1' => $this->getItemsInOrder1Query(),
            'itemsInOrder2' => $this->getItemsInOrder2Query(),
            'books' => $this->getBooksQuery(),
            'booksWithNullFK' => $this->getBooksWithNullFKQuery(),
            'booksViaTable' => $this->getBooksViaTableQuery(),
            'booksWithNullFKViaTable' => $this->getBooksWithNullFKViaTableQuery(),
            'books2' => $this->getBooks2Query(),
            'booksExplicit' => $this->getBooksExplicitQuery(),
            'booksExplicitA' => $this->getBooksExplicitAQuery(),
            'bookItems' => $this->getBookItemsQuery(),
            'movieItems' => $this->getMovieItemsQuery(),
            'limitedItems' => $this->getLimitedItemsQuery(),
            'expensiveItemsUsingViaWithCallable' => $this->getExpensiveItemsUsingViaWithCallableQuery(),
            'cheapItemsUsingViaWithCallable' => $this->getCheapItemsUsingViaWithCallableQuery(),
            'orderItemsFor8' => $this->getOrderItemsFor8Query(),
            'itemsFor8' => $this->getItemsFor8Query(),
            default => parent::relationQuery($name),
        };
    }

    public function setVirtualCustomerId(string|int|null $virtualCustomerId = null): void
    {
        $this->virtualCustomerId = $virtualCustomerId;
    }

    public function getVirtualCustomer(): Customer|null
    {
        return $this->activeRecord()->relation('virtualCustomer');
    }

    public function getVirtualCustomerQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasOne(Customer::class, ['id' => 'virtualCustomerId']);
    }

    public function getCustomer(): Customer|null
    {
        return $this->activeRecord()->relation('customer');
    }

    public function getCustomerQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasOne(Customer::class, ['id' => 'customer_id']);
    }

    public function getCustomerJoinedWithProfile(): Customer|null
    {
        return $this->activeRecord()->relation('customerJoinedWithProfile');
    }

    public function getCustomerJoinedWithProfileQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasOne(Customer::class, ['id' => 'customer_id'])->joinWith('profile');
    }

    public function getCustomerJoinedWithProfileIndexOrdered(): array
    {
        return $this->activeRecord()->relation('customerJoinedWithProfileIndexOrdered');
    }

    public function getCustomerJoinedWithProfileIndexOrderedQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(
            Customer::class,
            ['id' => 'customer_id']
        )->joinWith('profile')->orderBy(['profile.description' => SORT_ASC])->indexBy('name');
    }

    public function getCustomer2(): Customer|null
    {
        return $this->activeRecord()->relation('customer2');
    }

    public function getCustomer2Query(): ActiveQuery
    {
        return $this->activeRecord()->hasOne(Customer::class, ['id' => 'customer_id'])->inverseOf('orders2');
    }

    public function getOrderItems(): array
    {
        return $this->activeRecord()->relation('orderItems');
    }

    public function getOrderItemsQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(OrderItem::class, ['order_id' => 'id']);
    }

    public function getOrderItems2(): array
    {
        return $this->activeRecord()->relation('orderItems2');
    }

    public function getOrderItems2Query(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(OrderItem::class, ['order_id' => 'id'])->indexBy('item_id');
    }

    public function getOrderItems3(): array
    {
        return $this->activeRecord()->relation('orderItems3');
    }

    public function getOrderItems3Query(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(
            OrderItem::class,
            ['order_id' => 'id']
        )->indexBy(fn ($row) => $row['order_id'] . '_' . $row['item_id']);
    }

    public function getOrderItemsWithNullFK(): array
    {
        return $this->activeRecord()->relation('orderItemsWithNullFK');
    }

    public function getOrderItemsWithNullFKQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(OrderItemWithNullFK::class, ['order_id' => 'id']);
    }

    public function getItems(): array
    {
        return $this->activeRecord()->relation('items');
    }

    public function getItemsQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(Item::class, ['id' => 'item_id'])->via('orderItems', static function ($q) {
            // additional query configuration
        })->orderBy('item.id');
    }

    public function getItemsIndexed(): array
    {
        return $this->activeRecord()->relation('itemsIndexed');
    }

    public function getItemsIndexedQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(Item::class, ['id' => 'item_id'])->via('orderItems')->indexBy('id');
    }

    public function getItemsWithNullFK(): array
    {
        return $this->activeRecord()->relation('itemsWithNullFK');
    }

    public function getItemsWithNullFKQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(
            Item::class,
            ['id' => 'item_id']
        )->viaTable('order_item_with_null_fk', ['order_id' => 'id']);
    }

    public function getItemsInOrder1(): array
    {
        return $this->activeRecord()->relation('itemsInOrder1');
    }

    public function getItemsInOrder1Query(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(Item::class, ['id' => 'item_id'])->via('orderItems', static function ($q) {
            $q->orderBy(['subtotal' => SORT_ASC]);
        })->orderBy('name');
    }

    public function getItemsInOrder2(): array
    {
        return $this->activeRecord()->relation('itemsInOrder2');
    }

    public function getItemsInOrder2Query(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(Item::class, ['id' => 'item_id'])->via('orderItems', static function ($q) {
            $q->orderBy(['subtotal' => SORT_DESC]);
        })->orderBy('name');
    }

    public function getBooks(): array
    {
        return $this->activeRecord()->relation('books');
    }

    public function getBooksQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(Item::class, ['id' => 'item_id'])->via('orderItems')->where(['category_id' => 1]);
    }

    public function getBooksWithNullFK(): array
    {
        return $this->activeRecord()->relation('booksWithNullFK');
    }

    public function getBooksWithNullFKQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(
            Item::class,
            ['id' => 'item_id']
        )->via('orderItemsWithNullFK')->where(['category_id' => 1]);
    }

    public function getBooksViaTable(): array
    {
        return $this->activeRecord()->relation('booksViaTable');
    }

    public function getBooksViaTableQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(
            Item::class,
            ['id' => 'item_id']
        )->viaTable('order_item', ['order_id' => 'id'])->where(['category_id' => 1]);
    }

    public function getBooksWithNullFKViaTable(): array
    {
        return $this->activeRecord()->relation('booksWithNullFKViaTable');
    }

    public function getBooksWithNullFKViaTableQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(
            Item::class,
            ['id' => 'item_id']
        )->viaTable('order_item_with_null_fk', ['order_id' => 'id'])->where(['category_id' => 1]);
    }

    public function getBooks2(): array
    {
        return $this->activeRecord()->relation('books2');
    }

    public function getBooks2Query(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(
            Item::class,
            ['id' => 'item_id']
        )->onCondition(['category_id' => 1])->viaTable('order_item', ['order_id' => 'id']);
    }

    public function getBooksExplicit(): array
    {
        return $this->activeRecord()->relation('booksExplicit');
    }

    public function getBooksExplicitQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(
            Item::class,
            ['id' => 'item_id']
        )->onCondition(['category_id' => 1])->viaTable('order_item', ['order_id' => 'id']);
    }

    public function getBooksExplicitA(): array
    {
        return $this->activeRecord()->relation('booksExplicitA');
    }

    public function getBooksExplicitAQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(
            Item::class,
            ['id' => 'item_id']
        )->alias('bo')->onCondition(['bo.category_id' => 1])->viaTable('order_item', ['order_id' => 'id']);
    }

    public function getBookItems(): array
    {
        return $this->activeRecord()->relation('bookItems');
    }

    public function getBookItemsQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(
            Item::class,
            ['id' => 'item_id']
        )->alias('books')->onCondition(['books.category_id' => 1])->viaTable('order_item', ['order_id' => 'id']);
    }

    public function getMovieItems(): array
    {
        return $this->activeRecord()->relation('movieItems');
    }

    public function getMovieItemsQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(
            Item::class,
            ['id' => 'item_id']
        )->alias('movies')->onCondition(['movies.category_id' => 2])->viaTable('order_item', ['order_id' => 'id']);
    }

    public function getLimitedItems(): array
    {
        return $this->activeRecord()->relation('limitedItems');
    }

    public function getLimitedItemsQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(Item::class, ['id' => 'item_id'])->onCondition(['item.id' => [3, 5]])->via('orderItems');
    }

    public function getExpensiveItemsUsingViaWithCallable(): array
    {
        return $this->activeRecord()->relation('expensiveItemsUsingViaWithCallable');
    }

    public function getExpensiveItemsUsingViaWithCallableQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(Item::class, ['id' => 'item_id'])->via('orderItems', function (ActiveQuery $q) {
            $q->where(['>=', 'subtotal', 10]);
        });
    }

    public function getCheapItemsUsingViaWithCallable(): array
    {
        return $this->activeRecord()->relation('cheapItemsUsingViaWithCallable');
    }

    public function getCheapItemsUsingViaWithCallableQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(Item::class, ['id' => 'item_id'])->via('orderItems', function (ActiveQuery $q) {
            $q->where(['<', 'subtotal', 10]);
        });
    }

    public function getOrderItemsFor8(): array
    {
        return $this->activeRecord()->relation('orderItemsFor8');
    }

    public function getOrderItemsFor8Query(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(OrderItemWithNullFK::class, ['order_id' => 'id'])->andOnCondition(['subtotal' => 8.0]);
    }

    public function getItemsFor8(): array
    {
        return $this->activeRecord()->relation('itemsFor8');
    }

    public function getItemsFor8Query(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(Item::class, ['id' => 'item_id'])->via('orderItemsFor8');
    }
}
