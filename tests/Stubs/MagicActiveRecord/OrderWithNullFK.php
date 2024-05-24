<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\MagicalActiveRecord;

/**
 * Class Order.
 *
 * @property int $id
 * @property int $customer_id
 * @property int $created_at
 * @property string $total
 */
final class OrderWithNullFK extends MagicalActiveRecord
{
    public function getTableName(): string
    {
        return 'order_with_null_fk';
    }
}
