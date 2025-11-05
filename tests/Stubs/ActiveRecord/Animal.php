<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class Animal.
 */
class Animal extends ActiveRecord
{
    protected int $id;
    protected string $type;
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
