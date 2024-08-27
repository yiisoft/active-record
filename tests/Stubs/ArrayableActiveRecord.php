<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\ArrayableTrait;
use Yiisoft\Arrays\ArrayableInterface;

/**
 * Active Record class which implements {@see ActiveRecordInterface} and provides additional features like:
 *
 * @see ArrayableInterface to convert the object into an array;
 */
class ArrayableActiveRecord extends ActiveRecord implements ArrayableInterface
{
    use ArrayableTrait;
}
