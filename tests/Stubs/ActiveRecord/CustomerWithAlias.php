<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class Customer.
 *
 * @method CustomerQuery findBySql($sql, $params = []) static
 */
final class CustomerWithAlias extends ActiveRecord
{
    public const STATUS_ACTIVE = 1;
    public const STATUS_INACTIVE = 2;

    public int $status2;
    public float $sumTotal;

    public int $id;
    public string $name;
    public string $email;
    public string $address;
    public int $status;
    public int|null $profile_id;

    public function getTableName(): string
    {
        return 'customer';
    }
}
