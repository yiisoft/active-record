<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\Trait\EventsTrait;
use Yiisoft\ActiveRecord\Trait\LazyLoadGuardTrait;

final class CustomerLazyLoadGuardModel extends Customer
{
    use EventsTrait;
    use LazyLoadGuardTrait;

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'guardedOrders' => $this->getGuardedOrdersQuery(),
            default => parent::relationQuery($name),
        };
    }

    public function getGuardedOrders(): array
    {
        return $this->relation('guardedOrders');
    }

    public function getGuardedOrdersQuery(): ActiveQuery
    {
        return $this->hasMany(OrderLazyLoadGuardModel::class, ['customer_id' => 'id'])->orderBy('[[id]]');
    }
}
