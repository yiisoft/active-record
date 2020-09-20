<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

/**
 * Class Dog.
 */
final class Dog extends Animal
{
    public static function populateRecord($record, $row): void
    {
        parent::populateRecord($record, $row);

        $record->does = 'bark';
    }
}
