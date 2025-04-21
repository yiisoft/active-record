<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

/**
 * Class Dossier
 *
 * @property int $id
 * @property int $department_id
 * @property int $employee_id
 * @property string $summary
 * @property Employee $employee
 */
final class Dossier extends MagicActiveRecord
{
    public function tableName(): string
    {
        return 'dossier';
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
