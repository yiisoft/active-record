<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event\Handler;

use Attribute;
use DateTimeImmutable;

/**
 * Attribute for setting value of the date and time for properties before updating an existing record
 * in the database. By default, it sets the current date and time to the `updated_at` property.
 *
 * It can be applied to classes or properties, and it can be repeated for multiple properties.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class SetDateTimeOnUpdate extends SetValueOnUpdate
{
    public function __construct(
        mixed $value = null,
        string ...$propertyNames,
    ) {
        $value ??= static fn(): DateTimeImmutable => new DateTimeImmutable();

        if (empty($propertyNames)) {
            $propertyNames = ['updated_at'];
        }

        parent::__construct($value, ...$propertyNames);
    }
}
