<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\Db\Exception\InvalidCallException;
use Yiisoft\Db\Query\Query;

final class UnqueryableQueryMock extends Query
{
    public function one()
    {
        throw new InvalidCallException();
    }

    public function all(): array
    {
        throw new InvalidCallException();
    }
}
