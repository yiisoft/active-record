<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecordModel;

/**
 * Class Customer.
 */
final class CustomerWithAlias extends ActiveRecordModel
{
    public const STATUS_ACTIVE = 1;
    public const STATUS_INACTIVE = 2;

    public int $status2;
    public float $sumTotal;

    public int $id;
    public string $email;
    public string|null $name = null;
    public string|null $address = null;
    public int|null $status = null;
    public bool|string|null $bool_status = null;
    public int|null $profile_id = null;

    public function tableName(): string
    {
        return 'customer';
    }
}
