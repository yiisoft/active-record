<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Data;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\Db\Connectors\ConnectionPool;
use Yiisoft\Db\Contracts\ConnectionInterface;

/**
 * Class Employee
 *
 * @property int $id
 * @property int $department_id
 * @property string $first_name
 * @property string $last_name
 *
 * @property string $fullName
 * @property Department $department
 * @property Dossier $dossier
 */
class Employee extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'employee';
    }

    /**
     * Returns employee full name.
     *
     * @return string
     */
    public function getFullName()
    {
        $fullName = $this->first_name . ' ' . $this->last_name;

        return $fullName;
    }

    /**
     * Returns employee department.
     *
     * @return ActiveQuery
     */
    public function getDepartment()
    {
        return $this
            ->hasOne(Department::class, [
                'id' => 'department_id',
            ])
            ->inverseOf('employees')
        ;
    }

    /**
     * Returns employee department.
     *
     * @return ActiveQuery
     */
    public function getDossier()
    {
        return $this
            ->hasOne(Dossier::class, [
                'department_id' => 'department_id',
                'employee_id' => 'id',
            ])
            ->inverseOf('employee')
        ;
    }

    public static function getConnection(): ConnectionInterface
    {
        return ConnectionPool::getConnectionPool('mysql');
    }
}
