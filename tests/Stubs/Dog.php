<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs;

/**
 * Class Dog.
 */
class Dog extends Animal
{
    /**
     * @param self $record
     * @param array $row
     */
    public static function populateRecord($record, $row): void
    {
        parent::populateRecord($record, $row);

        $record->does = 'bark';
    }
}
