<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use ArrayAccess;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\ArrayableActiveRecord;
use Yiisoft\ActiveRecord\Trait\ArrayAccessTrait;

final class CustomerArrayAccessModel extends ArrayableActiveRecord implements ArrayAccess
{
    use ArrayAccessTrait;

    public ?int $id = null;
    public ?string $name = null;
    public ?string $email = null;
    public ?int $profile_id = null;
    protected ?int $status = 0;
    public mixed $customProperty = null;

    public function tableName(): string
    {
        return 'customer';
    }

    public function relationQuery(string $name): ActiveQuery
    {
        return match ($name) {
            'profile' => $this->hasOne(Profile::class, ['id' => 'profile_id']),
            'orders' => $this->hasMany(Order::class, ['customer_id' => 'id']),
            default => parent::relationQuery($name),
        };
    }
}
