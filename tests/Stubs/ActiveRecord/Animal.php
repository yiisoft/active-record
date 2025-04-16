<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class Animal.
 */
class Animal extends ActiveRecord
{
    private string $does;

    protected int $id;
    protected string $type;

    public function getTableName(): string
    {
        return 'animal';
    }

    public function __construct()
    {
        $this->type = static::class;
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
