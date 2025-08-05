<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\Db\QueryBuilder\Condition\Equals;

final class CustomerQuery extends ActiveQuery
{
    public bool $joinWithProfile = false;

    public function active(): self
    {
        $this->andWhere(new Equals('status', 1));

        return $this;
    }
}
