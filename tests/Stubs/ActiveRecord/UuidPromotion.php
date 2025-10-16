<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;

final class UuidPromotion extends ActiveRecord
{
    public string $id;
    /** @var string[] */
    public array $json_item_ids;
    public string $title;

    public function tableName(): string
    {
        return 'uuid_promotion';
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'itemsViaJson' => $this
                ->hasMany(UuidItem::class, ['id' => 'json_item_ids'])
                ->inverseOf('promotionsViaJson'),
            'itemsViaJsonIndexed' => $this
                ->hasMany(UuidItem::class, ['id' => 'json_item_ids'])
                ->indexBy('id'),
            default => parent::relationQuery($name),
        };
    }

    /**
     * @return UuidItem[]
     */
    public function getItemsViaJson(): array
    {
        return $this->relation('itemsViaJson');
    }

    /**
     * @return UuidItem[]
     */
    public function getItemsViaJsonIndexed(): array
    {
        return $this->relation('itemsViaJsonIndexed');
    }
}
