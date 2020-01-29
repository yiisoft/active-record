<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Data;
use Yiisoft\Db\Connectors\ConnectionPool;
use Yiisoft\Db\Contracts\ConnectionInterface;

/**
 * Class Dog.
 */
class Dog extends Animal
{
    /**
     * @param self $record
     * @param array $row
     */
    public static function populateRecord($record, $row)
    {
        parent::populateRecord($record, $row);

        $record->does = 'bark';
    }

    public static function getConnection(): ConnectionInterface
    {
        return ConnectionPool::getConnectionPool('mysql');
    }
}
