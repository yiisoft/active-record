<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Data;

use Yiisoft\ActiveRecord\Models\Model;

/**
 * Singer.
 */
class Singer extends Model
{
    public ?string $firstName = null;
    public ?string $lastName = null;
    public ?string $test = null;
}
