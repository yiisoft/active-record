<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\MagicalActiveRecord;

/**
 * Class Department
 *
 * @property int $id
 * @property string $title
 * @property Employee[] $employees
 */
final class Department extends MagicalActiveRecord
{
    public function getTableName(): string
    {
        return 'department';
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
