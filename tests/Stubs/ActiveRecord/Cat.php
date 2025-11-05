<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\Db\Exception\Exception;

final class Cat extends Animal
{
    public function populateRecord($row): static
    {
        parent::populateRecord($row);
        $this->setDoes('meow');
        return $this;
    }

    public function getException(): never
    {
        throw new Exception('no');
    }

    /**
     * This is to test if __isset catches the error.
     *
     * @throw DivisionByZeroError
     */
    public function getThrowable(): float|int
    {
        return 5 / 0;
    }

    public function setNonExistingProperty(string $value): void {}
}
