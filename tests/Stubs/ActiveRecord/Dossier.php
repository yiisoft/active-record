<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordModel;

/**
 * Class Dossier
 */
final class Dossier extends ActiveRecordModel
{
    protected int $id;
    protected int $department_id;
    protected int $employee_id;
    protected string $summary;

    public function tableName(): string
    {
        return 'dossier';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getDepartmentId(): int
    {
        return $this->department_id;
    }

    public function getEmployeeId(): int
    {
        return $this->employee_id;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setDepartmentId(int $departmentId): void
    {
        $this->activeRecord()->set('department_id', $departmentId);
    }

    public function setEmployeeId(int $employeeId): void
    {
        $this->activeRecord()->set('employee_id', $employeeId);
    }

    public function setSummary(string $summary): void
    {
        $this->summary = $summary;
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'employee' => $this->getEmployeeQuery(),
            default => parent::relationQuery($name),
        };
    }

    public function getEmployee(): Employee|null
    {
        return $this->activeRecord()->relation('employee');
    }

    public function getEmployeeQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasOne(
            Employee::class,
            [
                'department_id' => 'department_id',
                'id' => 'employee_id',
            ]
        )->inverseOf('dossier');
    }
}
