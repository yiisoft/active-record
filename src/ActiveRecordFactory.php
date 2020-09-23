<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Factory\Factory;

final class ActiveRecordFactory extends Factory
{
    private ConnectionInterface $connection;

    public function createAR(string $value): ActiveRecordInterface
    {
        return parent::create(
            [
                '__class' => $value
            ]
        );
    }

    public function createQueryTo(string $value): ActiveQueryInterface
    {
        return parent::create(
            [
                '__class' => ActiveQuery::class,
                '__construct()' => [
                    $value
                ]
            ]
        );
    }

    public function withConnection(ConnectionInterface $connection)
    {
        $this->set(ConnectionInterface::class, $connection);

        return $this;
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->get(ConnectionInterface::class);
    }
}
