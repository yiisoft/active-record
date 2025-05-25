<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;

class Promotion extends ActiveRecord
{
    public int $id;
    /** @var int[] $array_item_ids */
    public array $array_item_ids;
    /** @var int[] $json_item_ids */
    public array $json_item_ids;
    public string $title;

    public function tableName(): string
    {
        return '{{%promotion}}';
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'itemsViaJson' => $this->hasMany(Item::class, ['id' => 'json_item_ids'])
                ->inverseOf('promotionsViaJson'),
            default => parent::relationQuery($name),
        };
    }

    /** @return Item[] */
    public function getItemsViaJson(): array
    {
        return $this->relation('itemsViaJson');
    }
}
