<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Data;

use Yiisoft\ActiveRecord\ActiveQuery;

/**
 * CustomerQuery.
 */
class CustomerQuery extends ActiveQuery
{
    public static $joinWithProfile = false;

    public function active()
    {
        $this->andWhere('[[status]]=1');

        return $this;
    }
}
