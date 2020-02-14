<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs;

use Yiisoft\ActiveRecord\ActiveQuery;

/**
 * OrderItemWithConstructor.
 *
 * @property int $order_id
 * @property int $item_id
 * @property int $quantity
 * @property string $subtotal
 */
class OrderItemWithConstructor extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'order_item';
    }

    public function __construct($item_id, $quantity)
    {
        $this->item_id = $item_id;
        $this->quantity = $quantity;

        parent::__construct();
    }

    public static function instance($refresh = false)
    {
        return self::instantiate([]);
    }

    public static function instantiate($row): ActiveRecord
    {
        return (new \ReflectionClass(static::class))->newInstanceWithoutConstructor();
    }

    public function getOrder(): ActiveQuery
    {
        return $this->hasOne(OrderWithConstructor::class, ['id' => 'order_id']);
    }
}
