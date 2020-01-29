<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Data;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\Db\Connectors\ConnectionPool;
use Yiisoft\Db\Contracts\ConnectionInterface;

/**
 * Class Customer.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $address
 * @property int $status
 *
 * @method CustomerQuery findBySql($sql, $params = []) static
 */
class CustomerWithAlias extends ActiveRecord
{
    const STATUS_ACTIVE = 1;
    const STATUS_INACTIVE = 2;

    public $status2;

    public $sumTotal;

    public static function tableName()
    {
        return 'customer';
    }

    /**
     * {@inheritdoc}
     * @return CustomerQuery
     */
    public static function find()
    {
        $activeQuery = new CustomerQuery(get_called_class());

        $activeQuery->alias('csr');

        return $activeQuery;
    }

    public static function getConnection(): ConnectionInterface
    {
        return ConnectionPool::getConnectionPool('mysql');
    }
}
