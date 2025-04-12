<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Pgsql\Stubs;

/**
 * Model representing type table.
 */
final class Type extends \Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Type
{
    public int|null $bigint_col = null;
    public array|null $intarray_col = null;
    public array|null $textarray2_col = null;
    public array|null $jsonb_col = null;
    public array|null $jsonarray_col = null;
}
