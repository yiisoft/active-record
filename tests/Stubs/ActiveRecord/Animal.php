<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * Class Animal.
 *
 * @property int $id
 * @property string $type
 */
class Animal extends ActiveRecord
{
    public string $does;

    public function tableName(): string
    {
        return 'animal';
    }

    public function __construct(ConnectionInterface $db)
    {
        parent::__construct($db);
    }

    public function getDoes(): string
    {
        return $this->does;
    }
}
