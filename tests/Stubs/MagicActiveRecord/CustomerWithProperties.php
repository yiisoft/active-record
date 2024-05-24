<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\MagicalActiveRecord;

/**
 * Class Customer with defined properties.
 */
class CustomerWithProperties extends MagicalActiveRecord
{
    protected int $id;
    protected string $email;
    protected string|null $name = null;
    public string|null $address = null;

    public function getTableName(): string
    {
        return 'customer';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getName(): string|null
    {
        return $this->name;
    }

    public function getAddress(): string|null
    {
        return $this->address;
    }

    public function getStatus(): int|null
    {
        return $this->getAttribute('status');
    }

    public function getProfileQuery(): ActiveQuery
    {
        return $this->hasOne(Profile::class, ['id' => 'profile_id']);
    }

    public function getOrdersQuery(): ActiveQuery
    {
        return $this->hasMany(Order::class, ['customer_id' => 'id'])->orderBy('[[id]]');
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function setName(string|null $name): void
    {
        $this->name = $name;
    }

    public function setAddress(string|null $address): void
    {
        $this->address = $address;
    }

    public function setStatus(int|null $status): void
    {
        $this->setAttribute('status', $status);
    }
}
