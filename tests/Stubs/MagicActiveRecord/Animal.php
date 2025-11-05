<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

/**
 * Class Animal.
 *
 * @property int $id
 * @property string $type
 */
class Animal extends MagicActiveRecord
{
    private string $does;

    public function __construct()
    {
        $this->type = static::class;
    }

    public function tableName(): string
    {
        return 'animal';
    }

    public function getDoes()
    {
        return $this->does;
    }

    public function setDoes(string $value): void
    {
        $this->does = $value;
    }
}
