<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\Redis;

use Yiisoft\ActiveRecord\Redis\ActiveQuery;
use Yiisoft\ActiveRecord\Redis\ActiveRecord;

/**
 * Class Item.
 *
 * @property int $id
 * @property string $name
 * @property int $category_id
 */
final class Item extends ActiveRecord
{
    public function attributes(): array
    {
        return [
            'id',
            'name',
            'category_id'
        ];
    }

    public function getCategory(): ActiveQuery
    {
        $this->hasOne(Category::class, ['id' => 'category_id']);
    }
}
