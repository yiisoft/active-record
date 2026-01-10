<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use DateTimeImmutable;
use Yiisoft\ActiveRecord\Tests\Stubs\ArrayableActiveRecord;

/**
 * Class CustomerClosureField.
 */
final class CustomerClosureField extends ArrayableActiveRecord
{
    protected int $id;
    protected string $email;
    protected ?string $name = null;
    protected ?string $address = null;
    protected ?int $status = 0;
    protected bool|string|null $bool_status = false;
    protected ?DateTimeImmutable $registered_at = null;
    protected ?int $profile_id = null;

    public function tableName(): string
    {
        return 'customer';
    }

    public function fields(): array
    {
        return array_merge(
            parent::fields(),
            [
                'status'        => static fn(self $customer) => $customer->status === 1 ? 'active' : 'inactive',
                'registered_at' => static fn(self $customer) => $customer->registered_at?->format('Y-m-d\TH:i:s.uP'),
            ],
        );
    }
}
