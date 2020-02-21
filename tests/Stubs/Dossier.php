<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs;

use Yiisoft\ActiveRecord\ActiveQuery;

/**
 * Class Dossier
 *
 * @property int $id
 * @property int $department_id
 * @property int $employee_id
 * @property string $summary
 *
 * @property Employee $employee
 */
class Dossier extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return 'dossier';
    }

    /**
     * Returns dossier employee.
     *
     * @return ActiveQuery
     */
    public function getEmployee(): ActiveQuery
    {
        return $this
            ->hasOne(Employee::class, [
                'department_id' => 'department_id',
                'id' => 'employee_id',
            ])
            ->inverseOf('dossier')
        ;
    }
}
