<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Event\Handler\DefaultValue;
use Yiisoft\ActiveRecord\Trait\EventsTrait;

final class DefaultValueAr extends ActiveRecord
{
    use EventsTrait;

    public int $id;

    #[DefaultValue('unknown')]
    public ?string $name;

    public function tableName(): string
    {
        return 'tbl_default_value';
    }
}
