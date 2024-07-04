<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Pgsql\Stubs;

use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;

final class Promotion extends ActiveRecord
{
    public int $id;
    /** @var int[] $item_ids */
    public array $item_ids;
    public string $title;

    public function getTableName(): string
    {
        return '{{%promotion}}';
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'items' => $this->hasMany(Item::class, ['id' => 'item_ids'])->inverseOf('promotions'),
            default => parent::relationQuery($name),
        };
    }

    /** @return Item[] */
    public function getItems(): array
    {
        return $this->relation('items');
    }
}
