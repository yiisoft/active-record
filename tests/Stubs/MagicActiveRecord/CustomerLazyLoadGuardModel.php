<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\Trait\LazyLoadGuardTrait;

final class CustomerLazyLoadGuardModel extends Customer
{
    use LazyLoadGuardTrait;
}
