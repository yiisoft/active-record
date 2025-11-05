<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

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
final class Employee extends MagicActiveRecord
{
    public function tableName(): string
    {
        return 'employee';
    }

    public function getFullName(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getDepartmentQuery(): ActiveQuery
    {
        return $this
            ->hasOne(Department::class, [
                'id' => 'department_id',
            ])
            ->inverseOf('employees')
        ;
    }

    public function getDossierQuery(): ActiveQuery
    {
        return $this->hasOne(
            Dossier::class,
            [
                'department_id' => 'department_id',
                'employee_id' => 'id',
            ],
        )->inverseOf('employee');
    }
}
