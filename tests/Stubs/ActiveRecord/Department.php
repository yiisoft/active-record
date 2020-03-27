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
 *
 * @property Employee[] $employees
 */
class Department extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return 'department';
    }

    /**
     * Returns department employees.
     *
     * @return ActiveQuery
     */
    public function getEmployees(): ActiveQuery
    {
        return $this
            ->hasMany(Employee::class, [
                'department_id' => 'id',
            ])
            ->inverseOf('department')
        ;
    }
}
