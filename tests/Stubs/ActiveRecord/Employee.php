<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordModel;

/**
 * Class Employee
 */
final class Employee extends ActiveRecordModel
{
    protected int $id;
    protected int $department_id;
    protected string $first_name;
    protected string $last_name;

    public function tableName(): string
    {
        return 'employee';
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'department' => $this->getDepartmentQuery(),
            'dossier' => $this->getDossierQuery(),
            default => parent::relationQuery($name),
        };
    }

    public function getFullName(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getDepartment(): Department
    {
        return $this->activeRecord()->relation('department');
    }

    public function getDepartmentQuery(): ActiveQuery
    {
        return $this
            ->activeRecord()->hasOne(Department::class, [
                'id' => 'department_id',
            ])
            ->inverseOf('employees')
        ;
    }

    public function getDossier(): Dossier
    {
        return $this->activeRecord()->relation('dossier');
    }

    public function getDossierQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasOne(
            Dossier::class,
            [
                'department_id' => 'department_id',
                'employee_id' => 'id',
            ]
        )->inverseOf('employee');
    }
}
