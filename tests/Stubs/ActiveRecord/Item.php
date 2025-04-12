<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class Item.
 */
class Item extends ActiveRecord
{
    protected int $id;
    protected string $name;
    protected int $category_id;

    public function getTableName(): string
    {
        return 'item';
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'category' => $this->getCategoryQuery(),
            'promotionsViaJson' => $this->hasMany(Promotion::class, ['json_item_ids' => 'id']),
            default => parent::relationQuery($name),
        };
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCategoryId(): int
    {
        return $this->category_id;
    }

    public function getCategory(): Category
    {
        return $this->relation('category');
    }

    public function getCategoryQuery(): ActiveQuery
    {
        return $this->hasOne(Category::class, ['id' => 'category_id']);
    }

    /** @return Promotion[] */
    public function getPromotionsViaJson(): array
    {
        return $this->relation('promotionsViaJson');
    }
}
