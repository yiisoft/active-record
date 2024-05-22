<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;

final class CustomerQuery extends ActiveQuery
{
    public bool $joinWithProfile = false;

    public function active(): self
    {
        $this->andWhere('[[status]]=1');

        return $this;
    }
}
