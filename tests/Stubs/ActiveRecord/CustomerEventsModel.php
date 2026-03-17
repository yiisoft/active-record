<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\Trait\EventsTrait;

final class CustomerEventsModel extends Customer
{
    use EventsTrait;
}
