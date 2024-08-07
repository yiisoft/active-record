<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;
use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * Class Animal.
 *
 * @property int $id
 * @property string $type
 */
class Animal extends MagicActiveRecord
{
    private string $does;

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

    public function instantiate($row): ActiveRecordInterface
    {
        $class = $row['type'];

        return new $class($this->db());
    }

    public function setDoes(string $value): void
    {
        $this->does = $value;
    }
}
