<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class Customer.
 */
final class CustomerWithAlias extends ActiveRecord
{
    public const STATUS_ACTIVE = 1;
    public const STATUS_INACTIVE = 2;

    public int $status2;
    public float $sumTotal;

    public int $id;
    public string $email;
    public ?string $name = null;
    public ?string $address = null;
    public ?int $status = null;
    public bool|string|null $bool_status = null;
    public ?int $profile_id = null;

    public function tableName(): string
    {
        return 'customer';
    }
}
