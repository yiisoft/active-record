<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * Class Animal.
 *
 * @property int $id
 * @property string $type
 */
class Animal extends ActiveRecord
{
    private string $does;

    public function tableName(): string
    {
        return 'animal';
    }

    public function __construct(ConnectionInterface $db)
    {
        parent::__construct($db);

        $this->type = static::class;
    }

    public function getDoes()
    {
        return $this->does;
    }

    public function instantiate($row): ActiveRecordInterface
    {
        $class = $row['type'];

        return new $class($this->db);
    }

    public function setDoes(string $value): void
    {
        $this->does = $value;
    }
}
