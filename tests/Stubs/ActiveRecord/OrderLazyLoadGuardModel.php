<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\Tests\Stubs\Trait\ModelLazyLoadGuardTrait;

final class OrderLazyLoadGuardModel extends Order
{
    use ModelLazyLoadGuardTrait;
}
