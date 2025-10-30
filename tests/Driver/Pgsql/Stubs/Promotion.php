<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Pgsql\Stubs;

use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;

final class Promotion extends ActiveRecord
{
    public int $id;
    /** @var int[] $array_item_ids */
    public array $array_item_ids;
    /** @var int[] $json_item_ids */
    public array $json_item_ids;
    public string $title;

    public function tableName(): string
    {
        return '{{%pgsql_promotion}}';
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'itemsViaArray' => $this->hasMany(Item::class, ['id' => 'array_item_ids'])
                ->inverseOf('promotionsViaArray'),
            default => parent::relationQuery($name),
        };
    }

    /**
     * @return Item[]
     */
    public function getItemsViaArray(): array
    {
        return $this->relation('itemsViaArray');
    }
}
