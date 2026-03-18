<?php

namespace Yiisoft\ActiveRecord\Tests\Support;

final class MyService
{
    public function __construct(public readonly string $name = 'default') {}
}
