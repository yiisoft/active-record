<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Pgsql\Stubs;

use Yiisoft\ActiveRecord\ActiveQueryInterface;

/**
 * Class Item.
 */
final class Item extends \Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Item
{
    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'promotions' => $this->hasMany(Promotion::class, ['item_ids' => 'id']),
            default => parent::relationQuery($name),
        };
    }

    /** @return Promotion[] */
    public function getPromotions(): array
    {
        return $this->relation('promotions');
    }
}
