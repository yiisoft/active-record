<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Data;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\Db\Connectors\ConnectionPool;
use Yiisoft\Db\Contracts\ConnectionInterface;

/**
 * Class Cat.
 */
class Cat extends Animal
{
    /**
     * @param self $record
     * @param array $row
     */
    public static function populateRecord($record, $row)
    {
        parent::populateRecord($record, $row);

        $record->does = 'meow';
    }

    /**
     * This is to test if __isset catches the exception.
     * @throw DivisionByZeroError
     * @return float|int
     */
    public function getException()
    {
        throw new \Exception('no');
    }

    /**
     * This is to test if __isset catches the error.
     * @throw DivisionByZeroError
     * @return float|int
     */
    public function getThrowable()
    {
        return 5/0;
    }

    public static function getConnection(): ConnectionInterface
    {
        return ConnectionPool::getConnectionPool('mysql');
    }
}
