<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

class ItemWithProperyHooks extends Item
{
    public Category $category {
        get => $this->relation('category');
        set {
            $this->populateRelation('category', $value);
        }
    }
}
