<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

final class CustomerWithUpdateInternalOverride extends Customer
{
    protected function updateInternal(?array $properties = null): int
    {
        return 91;
    }
}
