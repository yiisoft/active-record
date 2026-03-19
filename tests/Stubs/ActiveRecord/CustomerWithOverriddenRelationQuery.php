<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveQuery\AlternativeActiveQuery;

final class CustomerWithOverriddenRelationQuery extends Customer
{
    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'profile' => $this->hasOne(Profile::class, ['id' => 'profile_id']),
            default => parent::relationQuery($name),
        };
    }

    protected function createRelationQuery(
        ActiveRecordInterface|string $modelClass,
        array $link,
        bool $multiple,
    ): ActiveQueryInterface
    {
        return new AlternativeActiveQuery($modelClass);
    }
}
