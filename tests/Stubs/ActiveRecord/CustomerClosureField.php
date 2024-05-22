<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class CustomerClosureField.
 */
final class CustomerClosureField extends ActiveRecord
{
    protected int $id;
    protected string $email;
    protected string|null $name;
    protected string|null $address;
    protected int|null $status = 0;
    protected bool|string|null $bool_status = false;
    protected int|null $profile_id;

    public function getTableName(): string
    {
        return 'customer';
    }

    public function fields(): array
    {
        $fields = parent::fields();

        $fields['status'] = static fn(self $customer) => $customer->status === 1 ? 'active' : 'inactive';

        return $fields;
    }
}
