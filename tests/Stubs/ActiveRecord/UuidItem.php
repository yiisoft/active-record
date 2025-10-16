<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;

final class UuidItem extends ActiveRecord
{
    public string $id;
    public string $name;

    public function tableName(): string
    {
        return 'uuid_item';
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'promotionsViaJson' => $this
                ->hasMany(UuidPromotion::class, ['json_item_ids' => 'id'])
                ->inverseOf('itemsViaJson'),
            'promotionsViaJsonIndexed' => $this
                ->hasMany(UuidPromotion::class, ['json_item_ids' => 'id'])
                ->indexBy('id'),
            default => parent::relationQuery($name),
        };
    }

    /**
     * @return UuidPromotion[]
     */
    public function getPromotionsViaJson(): array
    {
        return $this->relation('promotionsViaJson');
    }

    /**
     * @return UuidPromotion[]
     */
    public function getPromotionsViaJsonIndexed(): array
    {
        return $this->relation('promotionsViaJsonIndexed');
    }
}
