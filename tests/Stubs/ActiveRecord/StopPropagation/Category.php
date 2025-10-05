<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\StopPropagation;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Event\Handler\SetValueOnUpdate;
use Yiisoft\ActiveRecord\Trait\EventsTrait;

#[BeforeUpdateStopPropagation]
final class Category extends ActiveRecord
{
    use EventsTrait;

    public ?int $id;

    #[SetValueOnUpdate('xxx')]
    public string $name;

    public function tableName(): string
    {
        return 'category';
    }
}
