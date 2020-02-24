<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs;

use Yiisoft\ActiveRecord\ActiveQuery;

/**
 * Class Order.
 *
 * @property int $id
 * @property int $customer_id
 * @property int $created_at
 * @property string $total
 */
class Order extends ActiveRecord
{
    public static ?string $tableName = null;

    public static function tableName(): string
    {
        return static::$tableName ?: 'order';
    }

    public function getCustomer(): ActiveQuery
    {
        return $this->hasOne(Customer::class, ['id' => 'customer_id']);
    }

    public function getCustomerJoinedWithProfile(): ActiveQuery
    {
        return $this->hasOne(Customer::class, ['id' => 'customer_id'])
            ->joinWith('profile');
    }

    public function getCustomerJoinedWithProfileIndexOrdered(): ActiveQuery
    {
        return $this->hasMany(Customer::class, ['id' => 'customer_id'])
            ->joinWith('profile')->orderBy(['profile.description' => SORT_ASC])->indexBy('name');
    }

    public function getCustomer2(): ActiveQuery
    {
        return $this->hasOne(Customer::class, ['id' => 'customer_id'])->inverseOf('orders2');
    }

    public function getOrderItems(): ActiveQuery
    {
        return $this->hasMany(OrderItem::class, ['order_id' => 'id']);
    }

    public function getOrderItems2(): ActiveQuery
    {
        return $this->hasMany(OrderItem::class, ['order_id' => 'id'])
            ->indexBy('item_id');
    }

    public function getOrderItems3(): ActiveQuery
    {
        return $this->hasMany(OrderItem::class, ['order_id' => 'id'])
            ->indexBy(function ($row) {
                return $row['order_id'] . '_' . $row['item_id'];
            });
    }

    public function getOrderItemsWithNullFK(): ActiveQuery
    {
        return $this->hasMany(OrderItemWithNullFK::class, ['order_id' => 'id']);
    }

    public function getItems(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['id' => 'item_id'])
            ->via('orderItems', static function ($q) {
                // additional query configuration
            })->orderBy('item.id');
    }

    public function getItemsIndexed(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['id' => 'item_id'])
            ->via('orderItems')->indexBy('id');
    }

    public function getItemsWithNullFK(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['id' => 'item_id'])
            ->viaTable('order_item_with_null_fk', ['order_id' => 'id']);
    }

    public function getItemsInOrder1(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['id' => 'item_id'])
            ->via('orderItems', static function ($q) {
                $q->orderBy(['subtotal' => SORT_ASC]);
            })->orderBy('name');
    }

    public function getItemsInOrder2(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['id' => 'item_id'])
            ->via('orderItems', static function ($q) {
                $q->orderBy(['subtotal' => SORT_DESC]);
            })->orderBy('name');
    }

    public function getBooks(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['id' => 'item_id'])
            ->via('orderItems')
            ->where(['category_id' => 1]);
    }

    public function getBooksWithNullFK(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['id' => 'item_id'])
            ->via('orderItemsWithNullFK')
            ->where(['category_id' => 1]);
    }

    public function getBooksViaTable(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['id' => 'item_id'])
            ->viaTable('order_item', ['order_id' => 'id'])
            ->where(['category_id' => 1]);
    }

    public function getBooksWithNullFKViaTable(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['id' => 'item_id'])
            ->viaTable('order_item_with_null_fk', ['order_id' => 'id'])
            ->where(['category_id' => 1]);
    }

    public function getBooks2(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['id' => 'item_id'])
            ->onCondition(['category_id' => 1])
            ->viaTable('order_item', ['order_id' => 'id']);
    }

    public function getBooksExplicit(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['id' => 'item_id'])
            ->onCondition(['category_id' => 1])
            ->viaTable('order_item', ['order_id' => 'id']);
    }

    public function getBooksExplicitA(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['id' => 'item_id'])->alias('bo')
            ->onCondition(['bo.category_id' => 1])
            ->viaTable('order_item', ['order_id' => 'id']);
    }

    public function getBookItems(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['id' => 'item_id'])->alias('books')
            ->onCondition(['books.category_id' => 1])
            ->viaTable('order_item', ['order_id' => 'id']);
    }

    public function getMovieItems(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['id' => 'item_id'])->alias('movies')
            ->onCondition(['movies.category_id' => 2])
            ->viaTable('order_item', ['order_id' => 'id']);
    }

    public function getLimitedItems(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['id' => 'item_id'])
            ->onCondition(['item.id' => [3, 5]])
            ->via('orderItems');
    }

    public function beforeSave($insert): bool
    {
        if (parent::beforeSave($insert)) {
            $this->created_at = time();

            return true;
        }

        return false;
    }

    public function attributeLabels(): array
    {
        return [
            'customer_id' => 'Customer',
            'total' => 'Invoice Total',
        ];
    }

    public function activeAttributes(): array
    {
        return [
            0 => 'customer_id',
        ];
    }
}
