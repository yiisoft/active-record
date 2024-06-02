<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Mysql\Stubs;

/**
 * Model representing type table.
 */
final class Type extends \Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Type
{
    public string|null $enum_col = null;
}
