<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Pgsql\Stubs;

/**
 * Model representing type table.
 */
final class Type extends \Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Type
{
    public ?int $bigint_col = null;
    public ?array $intarray_col = null;
    public ?array $textarray2_col = null;
    public ?array $jsonb_col = null;
    public ?array $jsonarray_col = null;
}
