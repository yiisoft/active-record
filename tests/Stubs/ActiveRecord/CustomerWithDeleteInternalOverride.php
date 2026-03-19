<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

final class CustomerWithDeleteInternalOverride extends Customer
{
    protected function deleteInternal(): int
    {
        return 77;
    }
}
