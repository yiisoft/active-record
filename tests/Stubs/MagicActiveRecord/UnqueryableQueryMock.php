<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\Db\Exception\InvalidCallException;
use Yiisoft\Db\Query\Query;

final class UnqueryableQueryMock extends Query
{
    public function one(): mixed
    {
        throw new InvalidCallException('Invalid call');
    }

    public function all(): array
    {
        throw new InvalidCallException('Invalid call');
    }
}
