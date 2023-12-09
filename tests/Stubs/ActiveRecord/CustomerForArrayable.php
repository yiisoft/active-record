<?php

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class CustomerClosureField.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $address
 * @property int $status
 */
class CustomerForArrayable extends ActiveRecord
{
    public function getTableName(): string
    {
        return 'customer';
    }

    public function toArray(array $fields = [], array $expand = [], bool $recursive = true): array
    {
        $data = parent::toArray($fields, $expand, $recursive);

        $data['status'] = $this->status == 1 ? 'active' : 'inactive';

        return $data;
    }
}