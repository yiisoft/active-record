<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

/**
 * Class Dog.
 */
final class Dog extends Animal
{
    public function initialize(): void
    {
        $this->setDoes('bark');
    }
}
