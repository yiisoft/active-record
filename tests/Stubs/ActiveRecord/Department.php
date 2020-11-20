<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class Department
 *
 * @property int $id
 * @property string $title
 * @property Employee[] $employees
 */
final class Department extends ActiveRecord
{
    public function tableName(): string
    {
        return 'department';
    }

    public function getEmployees(): ActiveQuery
    {
        return $this->hasMany(
            Employee::class,
            [
                'department_id' => 'id',
            ]
        )->inverseOf('department');
    }
}
