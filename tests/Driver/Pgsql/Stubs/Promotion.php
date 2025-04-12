<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Pgsql\Stubs;

use Yiisoft\ActiveRecord\ActiveQueryInterface;

final class Promotion extends \Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Promotion
{
    public function getTableName(): string
    {
        return '{{%promotion}}';
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'itemsViaArray' => $this->hasMany(Item::class, ['id' => 'array_item_ids'])
                ->inverseOf('promotionsViaArray'),
            default => parent::relationQuery($name),
        };
    }

    /** @return Item[] */
    public function getItemsViaArray(): array
    {
        return $this->relation('itemsViaArray');
    }
}
