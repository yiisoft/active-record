<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\Db\Exception\Exception;

final class Cat extends Animal
{
    public function populateRecord($row): void
    {
        parent::populateRecord($row);

        $this->setDoes('meow');
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

    public function setNonExistingProperty(string $value): void
    {
    }
}
