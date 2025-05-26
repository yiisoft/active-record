<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

/**
 * Class Item.
 *
 * @property int $id
 * @property string $name
 * @property int $category_id
 */
final class Item extends MagicActiveRecord
{
    public function tableName(): string
    {
        return 'item';
    }

    public function getCategoryQuery(): ActiveQuery
    {
        return $this->hasOne(Category::class, ['id' => 'category_id']);
    }
}
