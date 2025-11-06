<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use DateTimeImmutable;
use DateTimeInterface;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\Event\Handler\DefaultDateTimeOnInsert;
use Yiisoft\ActiveRecord\Event\Handler\SetDateTimeOnUpdate;
use Yiisoft\ActiveRecord\Event\Handler\SoftDelete;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;
use Yiisoft\ActiveRecord\Trait\EventsTrait;

/**
 * Class Order.
 *
 * @property int $id
 * @property int $customer_id
 * @property int $created_at
 * @property int $updated_at
 * @property int $deleted_at
 * @property string $total
 */
#[DefaultDateTimeOnInsert]
#[SetDateTimeOnUpdate]
#[SoftDelete]
class Order extends MagicActiveRecord
{
    use EventsTrait;

    public const TABLE_NAME = 'order';

    private string|int|null $virtualCustomerId = null;

    public function tableName(): string
    {
        return self::TABLE_NAME;
    }

    public function getCreated_at(): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat('U', (string) $this->get('created_at'));
    }

    public function setCreated_at(DateTimeInterface|int $createdAt): void
    {
        $this->set('created_at', $createdAt instanceof DateTimeInterface
            ? $createdAt->getTimestamp()
            : $createdAt);
    }

    public function setVirtualCustomerId(string|int|null $virtualCustomerId = null): void
    {
        $this->virtualCustomerId = $virtualCustomerId;
    }

    public function getVirtualCustomerQuery()
    {
        return $this->hasOne(Customer::class, ['id' => 'virtualCustomerId']);
    }

    public function getCustomerQuery(): ActiveQuery
    {
        return $this->hasOne(Customer::class, ['id' => 'customer_id']);
    }

    public function getCustomerJoinedWithProfileQuery(): ActiveQuery
    {
        return $this->hasOne(Customer::class, ['id' => 'customer_id'])->joinWith('profile');
    }

    public function getCustomerJoinedWithProfileIndexOrderedQuery(): ActiveQuery
    {
        return $this->hasMany(
            Customer::class,
            ['id' => 'customer_id'],
        )->joinWith('profile')->orderBy(['profile.description' => SORT_ASC])->indexBy('name');
    }

    public function getCustomer2Query(): ActiveQuery
    {
        return $this->hasOne(Customer::class, ['id' => 'customer_id'])->inverseOf('orders2');
    }

    public function getOrderItemsQuery(): ActiveQuery
    {
        return $this->hasMany(OrderItem::class, ['order_id' => 'id']);
    }

    public function getOrderItems2Query(): ActiveQuery
    {
        return $this->hasMany(OrderItem::class, ['order_id' => 'id'])->indexBy('item_id');
    }

    public function getOrderItems3Query(): ActiveQuery
    {
        return $this->hasMany(
            OrderItem::class,
            ['order_id' => 'id'],
        )->indexBy(fn($row) => $row['order_id'] . '_' . $row['item_id']);
    }

    public function getOrderItemsWithNullFKQuery(): ActiveQuery
    {
        return $this->hasMany(OrderItemWithNullFK::class, ['order_id' => 'id']);
    }

    public function getItemsQuery(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['id' => 'item_id'])->via('orderItems', static function ($q) {
            // additional query configuration
        })->orderBy('item.id');
    }

    public function getItemsIndexedQuery(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['id' => 'item_id'])->via('orderItems')->indexBy('id');
    }

    public function getItemsWithNullFKQuery(): ActiveQuery
    {
        return $this->hasMany(
            Item::class,
            ['id' => 'item_id'],
        )->viaTable('order_item_with_null_fk', ['order_id' => 'id']);
    }

    public function getItemsInOrder1Query(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['id' => 'item_id'])->via('orderItems', static function ($q) {
            $q->orderBy(['subtotal' => SORT_ASC]);
        })->orderBy('name');
    }

    public function getItemsInOrder2Query(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['id' => 'item_id'])->via('orderItems', static function ($q) {
            $q->orderBy(['subtotal' => SORT_DESC]);
        })->orderBy('name');
    }

    public function getBooksQuery(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['id' => 'item_id'])->via('orderItems')->where(['category_id' => 1]);
    }

    public function getBooksWithNullFKQuery(): ActiveQuery
    {
        return $this->hasMany(
            Item::class,
            ['id' => 'item_id'],
        )->via('orderItemsWithNullFK')->where(['category_id' => 1]);
    }

    public function getBooksViaTableQuery(): ActiveQueryInterface
    {
        return $this
            ->hasMany(Item::class, ['id' => 'item_id'])
            ->viaTable('order_item', ['order_id' => 'id'])->where(['category_id' => 1]);
    }

    public function getBooksWithNullFKViaTableQuery(): ActiveQuery
    {
        return $this->hasMany(
            Item::class,
            ['id' => 'item_id'],
        )->viaTable('order_item_with_null_fk', ['order_id' => 'id'])->where(['category_id' => 1]);
    }

    public function getBooks2Query(): ActiveQuery
    {
        return $this->hasMany(
            Item::class,
            ['id' => 'item_id'],
        )->on(['category_id' => 1])->viaTable('order_item', ['order_id' => 'id']);
    }

    public function getBooksExplicitQuery(): ActiveQuery
    {
        return $this->hasMany(
            Item::class,
            ['id' => 'item_id'],
        )->on(['category_id' => 1])->viaTable('order_item', ['order_id' => 'id']);
    }

    public function getBooksExplicitAQuery(): ActiveQuery
    {
        return $this->hasMany(
            Item::class,
            ['id' => 'item_id'],
        )->alias('bo')->on(['bo.category_id' => 1])->viaTable('order_item', ['order_id' => 'id']);
    }

    public function getBookItemsQuery(): ActiveQuery
    {
        return $this->hasMany(
            Item::class,
            ['id' => 'item_id'],
        )->alias('books')->on(['books.category_id' => 1])->viaTable('order_item', ['order_id' => 'id']);
    }

    public function getMovieItemsQuery(): ActiveQuery
    {
        return $this->hasMany(
            Item::class,
            ['id' => 'item_id'],
        )->alias('movies')->on(['movies.category_id' => 2])->viaTable('order_item', ['order_id' => 'id']);
    }

    public function getLimitedItemsQuery(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['id' => 'item_id'])->on(['item.id' => [3, 5]])->via('orderItems');
    }

    public function getExpensiveItemsUsingViaWithCallableQuery(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['id' => 'item_id'])->via('orderItems', function (ActiveQuery $q) {
            $q->where(['>=', 'subtotal', 10]);
        });
    }

    public function getCheapItemsUsingViaWithCallableQuery(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['id' => 'item_id'])->via('orderItems', function (ActiveQuery $q) {
            $q->where(['<', 'subtotal', 10]);
        });
    }

    public function getOrderItemsFor8Query(): ActiveQuery
    {
        return $this->hasMany(OrderItemWithNullFK::class, ['order_id' => 'id'])->andOn(['subtotal' => 8.0]);
    }

    public function getItemsFor8Query(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['id' => 'item_id'])->via('orderItemsFor8');
    }
}
