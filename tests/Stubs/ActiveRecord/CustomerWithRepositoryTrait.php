<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\Trait\RepositoryTrait;

class CustomerWithRepositoryTrait extends Customer
{
    use RepositoryTrait;
}
