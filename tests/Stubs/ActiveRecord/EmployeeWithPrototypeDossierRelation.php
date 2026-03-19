<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\ActiveRecordInterface;

final class EmployeeWithPrototypeDossierRelation extends ActiveRecord
{
    protected int $id;
    protected int $department_id;
    protected string $first_name;
    protected string $last_name;

    public function __construct(
        private readonly ActiveRecordInterface $dossierPrototype,
    ) {}

    public function tableName(): string
    {
        return 'employee';
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'dossier' => $this->hasOne(
                clone $this->dossierPrototype,
                ['department_id' => 'department_id', 'employee_id' => 'id'],
            ),
            default => parent::relationQuery($name),
        };
    }

    public function isPrimaryKey(array $keys): bool
    {
        return array_is_list($keys) && parent::isPrimaryKey($keys);
    }
}
