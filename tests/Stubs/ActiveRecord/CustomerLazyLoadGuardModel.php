<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\Trait\EventsTrait;
use Yiisoft\ActiveRecord\Trait\LazyLoadGuardTrait;

final class CustomerLazyLoadGuardModel extends Customer
{
    use EventsTrait;
    use LazyLoadGuardTrait;
}
