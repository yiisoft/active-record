<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\EventsTrait;

final class CategoryEventsModel extends ActiveRecord
{
    use EventsTrait;

    public ?int $id = null;
    public ?string $name = null;

    public function tableName(): string
    {
        return 'category';
    }
}
