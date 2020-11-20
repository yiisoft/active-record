<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class Employee
 *
 * @property int $id
 * @property int $department_id
 * @property string $first_name
 * @property string $last_name
 * @property string $fullName
 * @property Department $department
 * @property Dossier $dossier
 */
final class Employee extends ActiveRecord
{
    public function tableName(): string
    {
        return 'employee';
    }

    public function getFullName(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getDepartment(): ActiveQuery
    {
        return $this
            ->hasOne(Department::class, [
                'id' => 'department_id',
            ])
            ->inverseOf('employees')
        ;
    }

    public function getDossier(): ActiveQuery
    {
        return $this->hasOne(
            Dossier::class,
            [
                'department_id' => 'department_id',
                'employee_id' => 'id',
            ]
        )->inverseOf('employee');
    }
}
