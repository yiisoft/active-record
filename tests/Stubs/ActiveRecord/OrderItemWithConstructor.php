<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use ReflectionClass;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * OrderItemWithConstructor.
 *
 * @property int $order_id
 * @property int $item_id
 * @property int $quantity
 * @property string $subtotal
 */
final class OrderItemWithConstructor extends ActiveRecord
{
    public function tableName(): string
    {
        return 'order_item';
    }

    public function __construct($item_id, $quantity)
    {
        $this->item_id = $item_id;
        $this->quantity = $quantity;
    }

    public static function instance($refresh = false): self
    {
        return self::instantiate([]);
    }

    public function instantiate($row): ActiveRecordInterface
    {
        return (new ReflectionClass(static::class))->newInstanceWithoutConstructor();
    }

    public function getOrder(): ActiveQuery
    {
        return $this->hasOne(OrderWithConstructor::class, ['id' => 'order_id']);
    }
}
