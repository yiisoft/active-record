<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use IteratorAggregate;
use Yiisoft\ActiveRecord\Tests\Stubs\ArrayableActiveRecord;
use Yiisoft\ActiveRecord\Trait\ArrayIteratorTrait;

final class CategoryArrayIteratorModel extends ArrayableActiveRecord implements IteratorAggregate
{
    use ArrayIteratorTrait;

    public ?int $id = null;
    public ?string $name = null;
    public bool $customProperty = false;

    public function tableName(): string
    {
        return 'category';
    }
}
