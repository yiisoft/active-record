<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class Item.
 *
 * @property int $id
 * @property string $name
 * @property int $category_id
 */
final class Item extends ActiveRecord
{
    public function getTableName(): string
    {
        return 'item';
    }

    public function getCategoryQuery(): ActiveQuery
    {
        return $this->hasOne(Category::class, ['id' => 'category_id']);
    }
}
