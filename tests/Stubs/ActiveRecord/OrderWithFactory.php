<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\Trait\FactoryTrait;

final class OrderWithFactory extends Order
{
    use FactoryTrait;

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'customerWithFactory' => $this->activeRecord()->hasOne(CustomerWithFactory::class, ['id' => 'customer_id']),
            'customerWithFactoryClosure' => $this->activeRecord()->hasOne(
                fn () => $this->factory->create(CustomerWithFactory::class),
                ['id' => 'customer_id']
            ),
            'customerWithFactoryInstance' => $this->activeRecord()->hasOne(
                $this->factory->create(CustomerWithFactory::class),
                ['id' => 'customer_id']
            ),
            default => parent::relationQuery($name),
        };
    }

    public function getCustomerWithFactory(): CustomerWithFactory|null
    {
        return $this->activeRecord()->relation('customerWithFactory');
    }

    public function getCustomerWithFactoryClosure(): CustomerWithFactory|null
    {
        return $this->activeRecord()->relation('customerWithFactoryClosure');
    }

    public function getCustomerWithFactoryInstance(): CustomerWithFactory|null
    {
        return $this->activeRecord()->relation('customerWithFactoryInstance');
    }
}
