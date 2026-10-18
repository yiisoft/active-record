<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\Trait;

use Yiisoft\ActiveRecord\Trait\LazyLoadGuardTrait;

/**
 * A trait of an application that uses the guard trait, instead of the model using it directly.
 */
trait ModelLazyLoadGuardTrait
{
    use LazyLoadGuardTrait;
}
