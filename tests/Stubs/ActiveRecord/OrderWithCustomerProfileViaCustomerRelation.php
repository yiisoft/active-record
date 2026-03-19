<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQueryInterface;

final class OrderWithCustomerProfileViaCustomerRelation extends Order
{
    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'customerProfileViaCustomer' => $this->getCustomerProfileViaCustomerQuery(),
            default => parent::relationQuery($name),
        };
    }
}
