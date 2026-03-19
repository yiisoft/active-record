<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecordInterface;

final class CustomerWithRefreshInternalOverride extends Customer
{
    protected function refreshInternal(
        array|ActiveRecordInterface|null $record = null,
    ): bool
    {
        $this->setName('refreshed-via-override');

        return true;
    }
}
