<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Support;

final class MyService
{
    public function __construct(public readonly string $name = 'default') {}
}
