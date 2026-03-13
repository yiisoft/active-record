<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use ArrayAccess;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Profile;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;
use Yiisoft\ActiveRecord\Trait\ArrayAccessTrait;

/**
 * @property int $id
 * @property string $name
 */
final class CategoryWithNameRelationArrayAccess extends MagicActiveRecord implements ArrayAccess
{
    use ArrayAccessTrait;

    public function tableName(): string
    {
        return 'category';
    }

    public function relationQuery(string $name): ActiveQuery
    {
        return match ($name) {
            'name' => $this->hasOne(Profile::class, ['id' => 'id']),
            default => parent::relationQuery($name),
        };
    }
}
