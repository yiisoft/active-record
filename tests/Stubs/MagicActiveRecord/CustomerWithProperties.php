<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

/**
 * Class Customer with defined properties.
 */
class CustomerWithProperties extends MagicActiveRecord
{
    protected int $id;
    protected string $email;
    protected ?string $name = null;
    public ?string $address = null;

    public function tableName(): string
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function getStatus(): ?int
    {
        return $this->get('status');
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

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function setAddress(?string $address): void
    {
        $this->address = $address;
    }

    public function setStatus(?int $status): void
    {
        $this->set('status', $status);
    }
}
