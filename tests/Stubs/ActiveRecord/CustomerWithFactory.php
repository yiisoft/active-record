<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\Trait\FactoryTrait;
use Yiisoft\Factory\Factory;

final class CustomerWithFactory extends Customer
{
    use FactoryTrait;

    public function __construct(Factory $factory)
    {
        $this->factory = $factory;
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'ordersWithFactory' => $this->hasMany(OrderWithFactory::class, ['customer_id' => 'id']),
            default => parent::relationQuery($name),
        };
    }

    /** @return OrderWithFactory[] */
    public function getOrdersWithFactory(): array
    {
        return $this->relation('ordersWithFactory');
    }
}
