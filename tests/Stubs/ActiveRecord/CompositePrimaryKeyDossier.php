<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;

final class CompositePrimaryKeyDossier extends ActiveRecord
{
    protected ?int $id;
    protected int $department_id;
    protected int $employee_id;
    protected string $summary;

    public function tableName(): string
    {
        return 'dossier';
    }

    public function primaryKey(): array
    {
        return ['department_id', 'employee_id'];
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'employee' => $this->hasOne(Employee::class, [
                'department_id' => 'department_id',
                'id' => 'employee_id',
            ]),
            default => parent::relationQuery($name),
        };
    }
}
