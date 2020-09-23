<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\Db\Exceptions\Exception;

final class Cat extends Animal
{
    public function populateRecord($record, $row): void
    {
        parent::populateRecord($record, $row);

        $record->does = 'meow';
    }

    public function getException(): void
    {
        throw new Exception('no');
    }

    /**
     * This is to test if __isset catches the error.
     *
     * @throw DivisionByZeroError
     *
     * @return float|int
     */
    public function getThrowable()
    {
        return 5/0;
    }
}
