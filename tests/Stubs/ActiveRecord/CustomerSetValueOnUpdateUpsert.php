<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Event\Handler\SetValueOnUpdate;
use Yiisoft\ActiveRecord\Trait\EventsTrait;

final class CustomerSetValueOnUpdateUpsert extends ActiveRecord
{
    use EventsTrait;

    public string $email;

    #[SetValueOnUpdate('Updated')]
    public ?string $name = null;

    public ?string $address = null;

    public function tableName(): string
    {
        return 'customer';
    }
}
