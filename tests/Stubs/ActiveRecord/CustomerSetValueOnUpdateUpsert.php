<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Event\Handler\SetValueOnUpdate;
use Yiisoft\ActiveRecord\Trait\EventsTrait;

final class CustomerSetValueOnUpdateUpsert extends ActiveRecord
{
    use EventsTrait;

    public int $id;
    public string $email;
    #[SetValueOnUpdate('Updated')]
    public ?string $name = null;
    public ?string $address = null;
    public ?int $status = 0;
    public bool|int|null $bool_status = false;
    public ?int $profile_id = null;

    public function tableName(): string
    {
        return 'customer';
    }
}
