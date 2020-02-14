<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs;

use Yiisoft\Db\Exceptions\Exception;

/**
 * Class Cat.
 */
class Cat extends Animal
{
    /**
     * @param self $record
     * @param array $row
     */
    public static function populateRecord($record, $row): void
    {
        parent::populateRecord($record, $row);

        $record->does = 'meow';
    }

    /**
     * This is to test if __isset catches the exception.
     * @throw DivisionByZeroError
     *
     * @throws Exception
     *
     * @return void
     */
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
