<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event\Handler;

use Attribute;
use DateTimeImmutable;

/**
 * Attribute for setting default value of the date and time for properties before inserting a new record into
 * the database. By default, it sets the current date and time to the `created_at` and `updated_at` properties.
 *
 * It can be applied to classes or properties, and it can be repeated for multiple properties.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class DefaultDateTimeOnInsert extends DefaultValueOnInsert
{
    public function __construct(
        mixed $value = null,
        string ...$propertyNames,
    ) {
        $value ??= static fn(): DateTimeImmutable => new DateTimeImmutable();

        if (empty($propertyNames)) {
            $propertyNames = ['created_at', 'updated_at'];
        }

        parent::__construct($value, ...$propertyNames);
    }
}
