<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\Trait\CustomConnectionTrait;

final class CustomerWithCustomConnection extends Customer
{
    use CustomConnectionTrait;
}
