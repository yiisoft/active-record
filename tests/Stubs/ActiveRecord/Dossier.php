<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class Dossier
 *
 * @property int $id
 * @property int $department_id
 * @property int $employee_id
 * @property string $summary
 * @property Employee $employee
 */
final class Dossier extends ActiveRecord
{
    public function getTableName(): string
    {
        return 'dossier';
    }

    public function getEmployeeQuery(): ActiveQuery
    {
        return $this->hasOne(
            Employee::class,
            [
                'department_id' => 'department_id',
                'id' => 'employee_id',
            ]
        )->inverseOf('dossier');
    }
}
