<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Event\Handler\DefaultValueOnInsert;
use Yiisoft\ActiveRecord\Trait\EventsTrait;

final class DefaultValueOnInsertAr extends ActiveRecord
{
    use EventsTrait;

    public int $id;

    #[DefaultValueOnInsert('Vasya')]
    public ?string $name = null;

    public function tableName(): string
    {
        return 'tbl_default_value';
    }
}
