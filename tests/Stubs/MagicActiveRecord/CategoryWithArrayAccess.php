<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use ArrayAccess;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;
use Yiisoft\ActiveRecord\Trait\ArrayAccessTrait;

/**
 * @property int $id
 * @property string $name
 */
final class CategoryWithArrayAccess extends MagicActiveRecord implements ArrayAccess
{
    use ArrayAccessTrait;

    public function tableName(): string
    {
        return 'category';
    }
}
