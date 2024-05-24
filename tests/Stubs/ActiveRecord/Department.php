<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * Class Department
 */
final class Department extends ActiveRecord
{
    protected int $id;
    protected string $title;

    public function getTableName(): string
    {
        return 'department';
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'employees' => $this->getEmployeesQuery(),
            default => parent::relationQuery($name),
        };
    }

    public function getEmployees(): ActiveRecordInterface
    {
        return $this->relation('employees');
    }

    public function getEmployeesQuery(): ActiveQuery
    {
        return $this->hasMany(
            Employee::class,
            [
                'department_id' => 'id',
            ]
        )->inverseOf('department');
    }
}
