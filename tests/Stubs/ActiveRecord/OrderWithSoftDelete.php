<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use DateTimeInterface;
use Yiisoft\ActiveRecord\Event\Handler\SoftDelete;

class OrderWithSoftDelete extends Order
{
    #[SoftDelete]
    protected int|DateTimeInterface|null $deleted_at;
}
