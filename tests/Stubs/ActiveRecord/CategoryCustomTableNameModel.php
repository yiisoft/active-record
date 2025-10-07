<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\CustomTableNameTrait;

final class CategoryCustomTableNameModel extends ActiveRecord
{
    use CustomTableNameTrait;

    public ?int $id = null;
    public ?string $name = null;
}
