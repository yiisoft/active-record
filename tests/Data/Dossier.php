<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Data;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\Db\Connectors\ConnectionPool;
use Yiisoft\Db\Contracts\ConnectionInterface;

/**
 * Class Dossier
 *
 * @property int $id
 * @property int $department_id
 * @property int $employee_id
 * @property string $summary
 *
 * @property Employee $employee
 */
class Dossier extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'dossier';
    }

    /**
     * Returns dossier employee.
     *
     * @return ActiveQuery
     */
    public function getEmployee()
    {
        return $this
            ->hasOne(Employee::class, [
                'department_id' => 'department_id',
                'id' => 'employee_id',
            ])
            ->inverseOf('dossier')
        ;
    }

    public static function getConnection(): ConnectionInterface
    {
        return ConnectionPool::getConnectionPool('mysql');
    }
}
