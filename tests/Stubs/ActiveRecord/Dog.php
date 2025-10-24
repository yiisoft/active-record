<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

/**
 * Class Dog.
 */
final class Dog extends Animal
{
    public function populateRecord($row): static
    {
        parent::populateRecord($row);
        $this->setDoes('bark');
        return $this;
    }
}
