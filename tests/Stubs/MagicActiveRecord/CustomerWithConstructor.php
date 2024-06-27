<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;
use Yiisoft\Aliases\Aliases;

/**
 * CustomerWithConstructor.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $address
 * @property int $status
 * @property ProfileWithConstructor $profile
 */
final class CustomerWithConstructor extends MagicActiveRecord
{
    public function __construct(private Aliases $aliases)
    {
    }

    public function getTableName(): string
    {
        return 'customer';
    }

    public function getProfileQuery(): ActiveQuery
    {
        return $this->hasOne(Profile::class, ['id' => 'profile_id']);
    }
}
