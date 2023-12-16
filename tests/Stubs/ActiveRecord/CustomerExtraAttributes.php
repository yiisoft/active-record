<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class CustomerClosureField.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $address
 * @property int $status
 * @property string $sex
 */
final class CustomerExtraAttributes extends ActiveRecord
{
    private array $extraAttributes = [
        'sex' => 'm',
    ];

    public function getTableName(): string
    {
        return 'customer';
    }

    public function getAttribute(string $name): mixed
    {
        if (array_key_exists($name, $this->extraAttributes)) {
            return $this->extraAttributes[$name];
        }

        return parent::getAttribute($name);
    }

    public function getAttributes(array $names = null, array $except = []): array
    {
        return array_merge(parent::getAttributes($names, $except), $this->extraAttributes);
    }

    public function hasAttribute($name): bool
    {
        return array_key_exists($name, $this->extraAttributes) || parent::hasAttribute($name);
    }
}
