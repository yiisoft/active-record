<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class Employee
 */
final class Employee extends ActiveRecord
{
    protected int $id;
    protected int $department_id;
    protected string $first_name;
    protected string $last_name;

    private string $fullName;

    public function tableName(): string
    {
        return 'employee';
    }

    public function populateRecord(object|array $row): static
    {
        $row = parent::populateRecord($row);
        $row->fullName = $row->first_name . ' ' . $row->last_name;
        return $row;
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
        return $this->fullName;
    }

    public function getDepartment(): Department
    {
        return $this->relation('department');
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

    public function getDossier(): Dossier
    {
        return $this->relation('dossier');
    }

    public function getDossierQuery(): ActiveQuery
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
