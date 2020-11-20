<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\Redis;

use Yiisoft\ActiveRecord\Redis\ActiveQuery;

final class CustomerQuery extends ActiveQuery
{
    public function active(): self
    {
        $this->andWhere(['status' => 1]);

        return $this;
    }
}
